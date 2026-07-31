<?php

/**
 * List Permissions Use Case Contract.
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

use App\Queries\Role\ListPermissionsQuery;
use Domain\Models\IPermission;
use Ds\Seq;
use Shared\Exceptions\Result;

/**
 * Lists the permission catalogue — every slug a use case declared at
 * WorkerStart.
 *
 * This is what makes the catalogue discoverable. Permissions stopped being a
 * fixed list the moment they started being declared in use case constructors,
 * so a client building a role editor has nowhere else to learn what may be
 * granted.
 *
 * Guarded by `permission:list`.
 *
 * @see ListPermissionsQuery What it takes.
 * @see \App\Services\Interno\ListPermissionsUseCase The implementation.
 * @see IUpdateRolePermissionsUseCase What consumes the slugs this returns.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IListPermissionsUseCase
{
    /**
     * Reads the catalogue, filtered when the query carries a search term.
     *
     * @param  ListPermissionsQuery  $query  Carries the caller and the optional
     *                                       search term.
     * @return Result<Seq<IPermission>> The whole catalogue in registration
     *                                  order, empty when nothing matched; a 403
     *                                  failure when the caller may not read it.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function execute(ListPermissionsQuery $query): Result;
}
