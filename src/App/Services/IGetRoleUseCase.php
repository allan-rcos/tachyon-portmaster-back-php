<?php

/**
 * Get Role Use Case Contract.
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

use App\Queries\Role\GetRoleQuery;
use Infra\Query\Role\RoleViewItem;
use Shared\Exceptions\Result;

/**
 * Reads one role by id, with its live user count.
 *
 * Follows the single-read shape documented on
 * {@see \App\Services\Interno\GetProductUseCase}.
 *
 * Guarded by `role:list` — the same slug {@see IListRolesUseCase} declares, not
 * a `role:read` of its own: seeing one role and seeing the list are one
 * privilege.
 *
 * @see GetRoleQuery What it takes.
 * @see \App\Services\Interno\GetRoleUseCase The implementation.
 * @see IListRolesUseCase The paged sibling, and the shared permission.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IGetRoleUseCase
{
    /**
     * Reads the role the query names.
     *
     * @param  GetRoleQuery  $query  Carries the caller and the id.
     * @return Result<RoleViewItem> The role read model, or 404 when not found; a
     *                              403 or 500 otherwise.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function execute(GetRoleQuery $query): Result;
}
