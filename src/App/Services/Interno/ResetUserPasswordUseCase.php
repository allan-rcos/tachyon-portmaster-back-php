<?php

/**
 * Reset User Password Use Case.
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
use App\Commands\User\ResetUserPasswordCommand;
use App\Services\IResetUserPasswordUseCase;
use Domain\Models\IUser;
use Domain\TableModules\IUserTM;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Infra\Repository\IUserRepository;
use Shared\Exceptions\Result;

/**
 * Resets another user's password administratively.
 *
 * Follows the update shape documented on {@see UpdateProductUseCase}.
 *
 * Unlike {@see ChangePasswordUseCase} it takes no {@see \Domain\TableModules\IAuthTM}
 * and verifies nothing: the caller is not the account's owner and could not
 * supply the current password. The permission stands in for that proof, which is
 * why the two are separate classes rather than one with a branch.
 *
 * @see IResetUserPasswordUseCase The contract this implements.
 * @see ChangePasswordUseCase The self-service counterpart.
 * @uses IUnitOfWork The boundary this opens and closes.
 * @uses IUserRepository Loads the user and persists the new hash.
 * @uses IUserTM Validates the new password and hashes it.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class ResetUserPasswordUseCase implements IResetUserPasswordUseCase
{
    use AuthorizesWithPermission;

    /**
     * Declares `user:change-password`.
     *
     * @param  IUnitOfWork  $unitOfWork  The boundary; never the connection.
     * @param  IViewCacheRepository  $views  Told to drop the group once the
     *                                       commit has landed.
     * @param  IUserRepository  $users  Read from and written to.
     * @param  IUserTM  $userTM  Validates and hashes the new password.
     * @param  IRegisterPermissionUseCase  $registrar  Consulted once, here.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IViewCacheRepository $views,
        private IUserRepository $users,
        private IUserTM $userTM,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'user:change-password');
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function execute(ResetUserPasswordCommand $command): Result
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

        $built = $this->userTM->resetPassword($user, $command->newPassword);
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

        // After the commit, never before: a read in between would repopulate
        // the cache from the state this write replaces.
        $this->views->invalidate(ViewCacheGroup::User);

        return Result::void();
    }
}
