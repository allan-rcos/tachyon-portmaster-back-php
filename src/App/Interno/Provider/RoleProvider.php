<?php

/**
 * Role Provider.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

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

/**
 * Builds the role feature's use cases.
 *
 * Roles are what carry permissions, so the writes wired here are the ones that
 * change what everyone else may do.
 *
 * See {@see FeatureProvider} for why the wiring is split this way and why
 * nothing here is memoized.
 *
 * @see FeatureProvider The base, and the registrar.
 * @see UserProvider Where roles are assigned to people.
 * @see \App\Interno\AppProvider What re-exports these.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final class RoleProvider extends FeatureProvider
{
    /**
     * Builds the {@see IListRolesUseCase} implementation.
     *
     * @copyright 2026 Tachyon
     */
    public function listRolesUseCase(): IListRolesUseCase
    {
        return new ListRolesUseCase($this->infra->queryRepository(), $this->registrar());
    }

    /**
     * Builds the {@see IGetRoleUseCase} implementation.
     *
     * @copyright 2026 Tachyon
     */
    public function getRoleUseCase(): IGetRoleUseCase
    {
        return new GetRoleUseCase($this->infra->queryRepository(), $this->registrar());
    }

    /**
     * Builds the {@see ICreateRoleUseCase} implementation.
     *
     * @copyright 2026 Tachyon
     */
    public function createRoleUseCase(): ICreateRoleUseCase
    {
        return new CreateRoleUseCase(
            $this->infra->unitOfWork(),
            $this->infra->roleRepository(),
            $this->domain->roleTM(),
            $this->registrar(),
        );
    }

    /**
     * Builds the {@see IUpdateRolePermissionsUseCase} implementation.
     *
     * @copyright 2026 Tachyon
     */
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
