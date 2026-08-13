<?php

/**
 * List Container Summaries Query.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App\Queries\Container;

use App\Context\UserContext;

/**
 * Lists containers with their manifests and recent telemetry attached.
 *
 * Follows the query shape documented on
 * {@see \App\Queries\Product\ListProductsQuery}.
 *
 * `$id` narrows the listing to one container, which is how a caller reads a
 * single container *with* its cargo — {@see GetContainerQuery} returns the
 * container alone. That is why this query has an id at all despite being a
 * listing.
 *
 * @see \App\Services\IListContainerSummariesUseCase What consumes it.
 * @see ListContainersQuery The cheaper listing, without cargo.
 * @see GetContainerQuery The single read, without cargo.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class ListContainerSummariesQuery
{
    /**
     * @param  UserContext  $context  The caller.
     * @param  string|null  $id  Base62 id to narrow to one container, or null
     *                           for all of them.
     * @param  string|null  $cursor  Continuation token, passed through unread.
     * @param  int|null  $limit  Page size, or null to let the DQL choose.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public UserContext $context,
        public ?string $id = null,
        public ?string $cursor = null,
        public ?int $limit = null,
    ) {
    }
}
