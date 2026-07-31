<?php

/**
 * Get Role Query.
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
 * Reads one role by id, with its live user count.
 *
 * Follows the query shape documented on
 * {@see \App\Queries\Product\ListProductsQuery}.
 *
 * @see \App\Services\IGetRoleUseCase What consumes it.
 * @see ListRolesQuery The paged sibling.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class GetRoleQuery
{
    /**
     * @param  UserContext  $context  The caller.
     * @param  string  $id  Base62 id of the role.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public UserContext $context,
        public string $id,
    ) {
    }
}
