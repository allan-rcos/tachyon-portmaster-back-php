<?php

declare(strict_types=1);

namespace App\Services\Interno;

use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Commands\User\UpdateUserRolesCommand;
use App\Services\IUpdateUserRolesUseCase;
use Domain\Models\IUser;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IUserRepository;
use Shared\Exceptions\Result;

final readonly class UpdateUserRolesUseCase implements IUpdateUserRolesUseCase
{
    use AuthorizesWithPermission;

    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IUserRepository $users,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'user:update-roles');
    }

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

        return Result::success($user);
    }
}
