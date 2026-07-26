<?php

declare(strict_types=1);

namespace App\Services\Interno;

use App\Commands\User\DeleteUserCommand;
use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Services\IDeleteUserUseCase;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IUserRepository;
use Shared\Exceptions\Result;

final readonly class DeleteUserUseCase implements IDeleteUserUseCase
{
    use AuthorizesWithPermission;

    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IUserRepository $users,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'user:delete');
    }

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

        return Result::void();
    }
}
