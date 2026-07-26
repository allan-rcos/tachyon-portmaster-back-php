<?php

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

final readonly class UpdateUserUseCase implements IUpdateUserUseCase
{
    use AuthorizesWithPermission;

    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IUserRepository $users,
        private IUserTM $userTM,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'user:update');
    }

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
