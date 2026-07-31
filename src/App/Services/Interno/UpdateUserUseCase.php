<?php

/**
 * Update User Use Case.
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
use App\Commands\User\UpdateUserCommand;
use App\Services\IUpdateUserUseCase;
use Domain\Models\IUser;
use Domain\TableModules\IUserTM;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IUserRepository;
use Shared\Exceptions\Result;

/**
 * Changes another user's profile, if the caller may and the domain allows it.
 *
 * Follows the update shape documented on {@see UpdateProductUseCase}. Profile
 * fields only: the loaded user's password hash and roles are carried across
 * untouched by the table module, which is what stops an update from silently
 * clearing either.
 *
 * @see IUpdateUserUseCase The contract this implements.
 * @see UpdateAccountUseCase The unguarded self-service counterpart.
 * @uses IUnitOfWork The boundary this opens and closes.
 * @uses IUserRepository Loads the existing user and persists the new state.
 * @uses IUserTM Validates and rebuilds.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class UpdateUserUseCase implements IUpdateUserUseCase
{
    use AuthorizesWithPermission;

    /**
     * Declares `user:update`.
     *
     * @param  IUnitOfWork  $unitOfWork  The boundary; never the connection.
     * @param  IUserRepository  $users  Read from and written to.
     * @param  IUserTM  $userTM  Validates and rebuilds the profile.
     * @param  IRegisterPermissionUseCase  $registrar  Consulted once, here.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IUserRepository $users,
        private IUserTM $userTM,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'user:update');
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function execute(UpdateUserCommand $command): Result
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

        $built = $this->userTM->update($user, $command->name, $command->email);
        if (!$built->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($built->getErrorId());
        }

        /** @var IUser $updated */
        $updated = $built->getValue();

        $persisted = $this->users->update($updated);
        if (!$persisted->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($persisted->getErrorId());
        }

        $commit = $this->unitOfWork->commit();
        if (!$commit->isSuccess()) {
            return Result::failure($commit->getErrorId());
        }

        return Result::success($updated);
    }
}
