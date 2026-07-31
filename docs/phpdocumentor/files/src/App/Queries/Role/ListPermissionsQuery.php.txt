<?php

/**
 * List Permissions Query.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App\Queries\Role;

use App\Context\UserContext;

/**
 * Lists the registered permission slugs, optionally narrowed by a search term.
 *
 * No cursor and no limit, unlike its neighbour {@see ListRolesQuery}: the
 * catalogue is filled at WorkerStart by the use cases that declare their own
 * permissions, so it is bounded by how much code exists rather than by how much
 * data was entered. Paging a list that short would cost a round-trip to save
 * nothing.
 *
 * Sits with the role queries because that is what the catalogue is *for*: the
 * slugs it returns are exactly what
 * {@see \App\Commands\Role\UpdateRolePermissionsCommand} may hand back.
 *
 * @see \App\Services\IListPermissionsUseCase What consumes it.
 * @see ListRolesQuery The neighbour whose permissions these are.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class ListPermissionsQuery
{
    /**
     * @param  UserContext  $context  The caller.
     * @param  string|null  $search  Free text matched against the slug,
     *                               unnormalised; null returns the whole
     *                               catalogue.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public UserContext $context,
        public ?string $search = null,
    ) {
    }
}
