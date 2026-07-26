<?php

declare(strict_types=1);

namespace App\Services\Interno;

use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Commands\Role\CreateRoleCommand;
use App\Services\ICreateRoleUseCase;
use Domain\Models\IRole;
use Domain\TableModules\IRoleTM;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IRoleRepository;
use Shared\Exceptions\Result;

final readonly class CreateRoleUseCase implements ICreateRoleUseCase
{
    use AuthorizesWithPermission;

    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IRoleRepository $roles,
        private IRoleTM $roleTM,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'role:create');
    }

    public function execute(CreateRoleCommand $command): Result
    {
        $denied = $this->authorize($command->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        $begin = $this->unitOfWork->begin();
        if (!$begin->isSuccess()) {
            return Result::failure($begin->getErrorId());
        }

        $built = $this->roleTM->create($command->name, $command->permissions);
        if (!$built->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($built->getErrorId());
        }

        /** @var IRole $role */
        $role = $built->getValue();

        $inserted = $this->roles->insert($role);
        if (!$inserted->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($inserted->getErrorId());
        }

        $commit = $this->unitOfWork->commit();
        if (!$commit->isSuccess()) {
            return Result::failure($commit->getErrorId());
        }

        return Result::success($role);
    }
}
