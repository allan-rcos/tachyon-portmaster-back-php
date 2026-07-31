<?php

/**
 * List Roles Query.
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
 * Lists roles, each with its live user count.
 *
 * Follows the query shape documented on
 * {@see \App\Queries\Product\ListProductsQuery} — identical parameters.
 *
 * @see \App\Services\IListRolesUseCase What consumes it.
 * @see GetRoleQuery The single-read sibling.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class ListRolesQuery
{
    /**
     * @param  UserContext  $context  The caller.
     * @param  string|null  $cursor  Continuation token, passed through unread.
     * @param  int|null  $limit  Page size, or null to let the DQL choose.
     * @param  string|null  $search  Free text to filter names by, unnormalised.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public UserContext $context,
        public ?string $cursor = null,
        public ?int $limit = null,
        public ?string $search = null,
    ) {
    }
}
