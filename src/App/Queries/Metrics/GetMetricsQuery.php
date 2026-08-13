<?php

/**
 * Get Metrics Query.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App\Queries\Metrics;

use App\Context\UserContext;

/**
 * Reads the yard-wide metrics snapshot. Takes no parameters beyond the caller —
 * the context is what the use case authorizes against.
 *
 * @see \App\Services\IGetMetricsUseCase What consumes it.
 * @see \App\Queries\Product\ListProductsQuery The query shape.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class GetMetricsQuery
{
    /**
     * @param  UserContext  $context  The caller. The whole of the query — the
     *                                snapshot covers the entire yard, so there
     *                                is nothing to narrow.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public UserContext $context,
    ) {
    }
}
