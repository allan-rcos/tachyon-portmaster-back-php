<?php

/**
 * Role Table Module Contract.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

namespace Domain\TableModules;

use Domain\Models\IRole;
use Shared\Exceptions\Result;

/**
 * The rules a role must satisfy.
 *
 * Permission slugs are carried through as given: whether a slug names a
 * registered permission is a question only the registry can answer, and the
 * registry is infrastructure.
 *
 * @see IRole What gets built.
 * @see \Domain\TableModules\Interno\RoleTM The implementation.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IRoleTM
{
    /**
     * Builds a new role, assigning it an id.
     *
     * @param  string  $name  Display name; required, at most 255 characters.
     * @param  list<string>  $permissions  Permission slugs to grant. May be
     *                                     empty — a role granting nothing is
     *                                     valid, if useless.
     * @return Result<IRole> A 422 failure when the name breaks a rule.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function create(
        string $name,
        array $permissions,
    ): Result;

    /**
     * Produces the role with its permission set **replaced** by the given one.
     *
     * A full replacement, not a merge: an omitted slug is a revoked permission.
     * That is what makes the endpoint behind this idempotent — sending the same
     * list twice leaves the same role.
     *
     * @param  IRole  $role  The role being modified.
     * @param  list<string>  $permissions  The complete new set of slugs.
     * @return Result<IRole> Always a success; the name is untouched, so there is
     *                       nothing here that can break a rule.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function updatePermissions(
        IRole $role,
        array $permissions,
    ): Result;
}
