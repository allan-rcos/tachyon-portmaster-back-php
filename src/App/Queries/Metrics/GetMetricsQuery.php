<?php

declare(strict_types=1);

namespace App\Queries\Metrics;

use App\Context\UserContext;

/**
 * Reads the yard-wide metrics snapshot. Takes no parameters beyond the caller —
 * the context is what the use case authorizes against.
 */
final readonly class GetMetricsQuery
{
    public function __construct(
        public UserContext $context,
    ) {
    }
}
