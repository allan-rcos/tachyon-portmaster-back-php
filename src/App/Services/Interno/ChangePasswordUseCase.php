<?php

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

final readonly class ChangePasswordUseCase implements IChangePasswordUseCase
{
    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IUserRepository $users,
        private IAuthTM $authTM,
        private IUserTM $userTM,
    ) {
    }

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
