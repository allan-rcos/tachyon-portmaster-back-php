<?php

declare(strict_types=1);

namespace App\Services\Interno;

use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Commands\User\ResetUserPasswordCommand;
use App\Services\IResetUserPasswordUseCase;
use Domain\Models\IUser;
use Domain\TableModules\IUserTM;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IUserRepository;
use Shared\Exceptions\Result;

final readonly class ResetUserPasswordUseCase implements IResetUserPasswordUseCase
{
    use AuthorizesWithPermission;

    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IUserRepository $users,
        private IUserTM $userTM,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'user:change-password');
    }

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

        return Result::void();
    }
}
