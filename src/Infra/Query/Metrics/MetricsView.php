<?php

declare(strict_types=1);

namespace Infra\Query\Metrics;

final readonly class MetricsView
{
    public function __construct(
        public int $activeContainers,
        public int $totalContainers,
        public float $yardLoad,
        public int $registeredProducts,
        public OccupancyView $occupancy,
    ) {
    }
}
