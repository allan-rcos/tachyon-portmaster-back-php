<?php

/**
 * Update User Roles Use Case.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App\Services\Interno;

use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Commands\User\UpdateUserRolesCommand;
use App\Services\IUpdateUserRolesUseCase;
use Domain\Models\IUser;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Infra\Repository\IUserRepository;
use Shared\Exceptions\Result;

/**
 * Replaces which roles a user holds, if the caller may.
 *
 * Follows the update shape documented on {@see UpdateProductUseCase}, without a
 * table module: which roles a user holds is a fact about the pivot, not a rule
 * the domain has anything to say about. The load is there to make an absent user
 * a 404.
 *
 * The write itself is a full sync — see
 * {@see \Infra\Repository\IUserRepository::syncRoles()} — so the boundary is
 * what keeps a user from being briefly role-less if it fails halfway.
 *
 * @see IUpdateUserRolesUseCase The contract this implements.
 * @see UpdateProductUseCase The shape.
 * @uses IUnitOfWork The boundary this opens and closes.
 * @uses IUserRepository Loads the user, then rewrites their assignments.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class UpdateUserRolesUseCase implements IUpdateUserRolesUseCase
{
    use AuthorizesWithPermission;

    /**
     * Declares `user:update-roles`, separate from `user:update`.
     *
     * @param  IUnitOfWork  $unitOfWork  The boundary; never the connection.
     * @param  IViewCacheRepository  $views  Told to drop the group once the
     *                                       commit has landed.
     * @param  IUserRepository  $users  Loads the user, then rewrites the pivot.
     * @param  IRegisterPermissionUseCase  $registrar  Consulted once, here.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IViewCacheRepository $views,
        private IUserRepository $users,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'user:update-roles');
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function execute(UpdateUserRolesCommand $command): Result
    {
        $denied = $this->authorize($command->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        $begin = $this->unitOfWork->begin();
        if (!$begin->isSuccess()) {
            return Result::failure($begin->getErrorId());
        }

        $existing = $this->users->findById($command->id);
        if (!$existing->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($existing->getErrorId());
        }

        /** @var IUser $user */
        $user = $existing->getValue();

        $synced = $this->users->syncRoles($user->id, $command->roleIds);
        if (!$synced->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($synced->getErrorId());
        }

        $commit = $this->unitOfWork->commit();
        if (!$commit->isSuccess()) {
            return Result::failure($commit->getErrorId());
        }

        // After the commit, never before: a read in between would repopulate
        // the cache from the state this write replaces.
        $this->views->invalidate(ViewCacheGroup::User);

        return Result::success($user);
    }
}
