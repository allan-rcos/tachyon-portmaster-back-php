<?php

/**
 * Delete User Use Case.
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

use App\Commands\User\DeleteUserCommand;
use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Services\IDeleteUserUseCase;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Infra\Repository\IUserRepository;
use Shared\Exceptions\Result;

/**
 * Removes a user, if the caller may.
 *
 * Follows the delete shape documented on {@see DeleteProductUseCase}: load so an
 * absent user is a 404, then delete. Their role assignments go with them by
 * cascade.
 *
 * @see IDeleteUserUseCase The contract this implements.
 * @see DeleteProductUseCase The shape.
 * @uses IUnitOfWork The boundary this opens and closes.
 * @uses IUserRepository Loads the user, then removes them.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class DeleteUserUseCase implements IDeleteUserUseCase
{
    use AuthorizesWithPermission;

    /**
     * Declares `user:delete`. Takes no table module — there is no rule to
     * consult about a removal.
     *
     * @param  IUnitOfWork  $unitOfWork  The boundary; never the connection.
     * @param  IViewCacheRepository  $views  Told to drop the group once the
     *                                       commit has landed.
     * @param  IUserRepository  $users  Read from, then deleted from.
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
        $this->permission = $this->declarePermission($registrar, 'user:delete');
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function execute(DeleteUserCommand $command): Result
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

        // Drop role assignments first so no orphan pivot rows remain.
        $synced = $this->users->syncRoles($command->id, []);
        if (!$synced->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($synced->getErrorId());
        }

        $deleted = $this->users->delete($command->id);
        if (!$deleted->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($deleted->getErrorId());
        }

        $commit = $this->unitOfWork->commit();
        if (!$commit->isSuccess()) {
            return Result::failure($commit->getErrorId());
        }

        // After the commit, never before: a read in between would repopulate
        // the cache from the state this write replaces.
        $this->views->invalidate(ViewCacheGroup::User);

        return Result::void();
    }
}
