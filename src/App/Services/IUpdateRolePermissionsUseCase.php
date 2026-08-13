<?php

/**
 * Update Role Permissions Use Case Contract.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App\Services;

use App\Commands\Role\UpdateRolePermissionsCommand;
use Domain\Models\IRole;
use Shared\Exceptions\Result;

/**
 * Replaces the set of permissions a role grants.
 *
 * Follows the update shape documented on {@see IUpdateProductUseCase}.
 *
 * The most consequential write in the layer: every user holding the role is
 * affected on their next request, and a caller holding this permission can
 * grant themselves any other.
 *
 * Guarded by `role:update-permissions` — separate from `role:create`, so
 * creating roles and re-arming existing ones are distinct privileges.
 *
 * @see UpdateRolePermissionsCommand What it takes.
 * @see \App\Services\Interno\UpdateRolePermissionsUseCase The implementation.
 * @see IUpdateProductUseCase The shape.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IUpdateRolePermissionsUseCase
{
    /**
     * Replaces what the role the command names grants.
     *
     * @param  UpdateRolePermissionsCommand  $command  Carries the caller, the id
     *                                                 and the whole new slug
     *                                                 set.
     * @return Result<IRole> The updated role, or 404 when it does not exist; 422
     *                       when a slug is refused, a 403 or 500 otherwise.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function execute(UpdateRolePermissionsCommand $command): Result;
}
