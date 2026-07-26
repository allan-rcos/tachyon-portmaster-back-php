<?php

declare(strict_types=1);

namespace Infra\Query\Metrics;

final readonly class OccupancyView
{
    public function __construct(
        public int $empty,
        public int $loading,
        public int $sealed,
        public int $inTransit,
    ) {
    }
}
