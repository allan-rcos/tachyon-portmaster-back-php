<?php

declare(strict_types=1);

namespace App\Interno\Provider;

use App\Services\ICreateRoleUseCase;
use App\Services\IGetRoleUseCase;
use App\Services\IListRolesUseCase;
use App\Services\Interno\CreateRoleUseCase;
use App\Services\Interno\GetRoleUseCase;
use App\Services\Interno\ListRolesUseCase;
use App\Services\Interno\UpdateRolePermissionsUseCase;
use App\Services\IUpdateRolePermissionsUseCase;

final class RoleProvider extends FeatureProvider
{
    public function listRolesUseCase(): IListRolesUseCase
    {
        return new ListRolesUseCase($this->infra->queryRepository(), $this->registrar());
    }

    public function getRoleUseCase(): IGetRoleUseCase
    {
        return new GetRoleUseCase($this->infra->queryRepository(), $this->registrar());
    }

    public function createRoleUseCase(): ICreateRoleUseCase
    {
        return new CreateRoleUseCase(
            $this->infra->unitOfWork(),
            $this->infra->roleRepository(),
            $this->domain->roleTM(),
            $this->registrar(),
        );
    }

    public function updateRolePermissionsUseCase(): IUpdateRolePermissionsUseCase
    {
        return new UpdateRolePermissionsUseCase(
            $this->infra->unitOfWork(),
            $this->infra->roleRepository(),
            $this->domain->roleTM(),
            $this->registrar(),
        );
    }
}
