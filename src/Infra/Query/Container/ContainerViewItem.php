<?php

declare(strict_types=1);

namespace Infra\Query\Container;

use Domain\Enums\ContainerStatus;

final readonly class ContainerViewItem
{
    public function __construct(
        public string $id,
        public string $code,
        public float $currentWeight,
        public float $maxCapacity,
        public ContainerStatus $status,
    ) {
    }
}
