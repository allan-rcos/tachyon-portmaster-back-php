<?php

/**
 * Change Password Use Case.
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

use App\Commands\Account\ChangePasswordCommand;
use App\Services\IChangePasswordUseCase;
use Domain\Models\IUser;
use Domain\TableModules\IAuthTM;
use Domain\TableModules\IUserTM;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IUserRepository;
use Shared\Exceptions\Result;

/**
 * Changes the caller's own password.
 *
 * Follows the update shape documented on {@see UpdateProductUseCase}, with the
 * current password verified between the load and the rebuild — the only use case
 * here that consults {@see IAuthTM}.
 *
 * Declares no permission, and takes the target from the context rather than the
 * command: a caller can only ever change their own. What guards it is the
 * current password, which is why a stolen session cannot be used to lock the
 * owner out.
 *
 * @see IChangePasswordUseCase The contract this implements.
 * @see ResetUserPasswordUseCase The administrative counterpart, which verifies nothing.
 * @uses IUnitOfWork The boundary this opens and closes.
 * @uses IUserRepository Loads the caller and persists the new hash.
 * @uses IAuthTM Verifies the current password against the stored hash.
 * @uses IUserTM Validates the new password and hashes it.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class ChangePasswordUseCase implements IChangePasswordUseCase
{
    /**
     * Takes no registrar: there is no permission to declare.
     *
     * @param  IUnitOfWork  $unitOfWork  The boundary; never the connection.
     * @param  IUserRepository  $users  Read from and written to.
     * @param  IAuthTM  $authTM  Checks the supplied current password.
     * @param  IUserTM  $userTM  Validates and hashes the new one.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IUserRepository $users,
        private IAuthTM $authTM,
        private IUserTM $userTM,
    ) {
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function execute(ChangePasswordCommand $command): Result
    {
        $begin = $this->unitOfWork->begin();
        if (!$begin->isSuccess()) {
            return Result::failure($begin->getErrorId());
        }

        $existing = $this->users->findById($command->context->id);
        if (!$existing->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($existing->getErrorId());
        }

        /** @var IUser $user */
        $user = $existing->getValue();

        // Verify the current password before allowing the change.
        $verified = $this->authTM->login($user, $command->currentPassword);
        if (!$verified->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($verified->getErrorId());
        }

        $built = $this->userTM->changePassword($user, $command->newPassword);
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

        return Result::void();
    }
}
