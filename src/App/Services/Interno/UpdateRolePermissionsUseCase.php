<?php

declare(strict_types=1);

namespace App\Services\Interno;

use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Commands\Role\UpdateRolePermissionsCommand;
use App\Services\IUpdateRolePermissionsUseCase;
use Domain\Models\IRole;
use Domain\TableModules\IRoleTM;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IRoleRepository;
use Shared\Exceptions\Result;

final readonly class UpdateRolePermissionsUseCase implements IUpdateRolePermissionsUseCase
{
    use AuthorizesWithPermission;

    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IRoleRepository $roles,
        private IRoleTM $roleTM,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'role:update-permissions');
    }

    public function execute(UpdateRolePermissionsCommand $command): Result
    {
        $denied = $this->authorize($command->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        $begin = $this->unitOfWork->begin();
        if (!$begin->isSuccess()) {
            return Result::failure($begin->getErrorId());
        }

        $existing = $this->roles->findById($command->id);
        if (!$existing->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($existing->getErrorId());
        }

        /** @var IRole $role */
        $role = $existing->getValue();

        $built = $this->roleTM->updatePermissions($role, $command->permissions);
        if (!$built->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($built->getErrorId());
        }

        /** @var IRole $updated */
        $updated = $built->getValue();

        $persisted = $this->roles->update($updated);
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
