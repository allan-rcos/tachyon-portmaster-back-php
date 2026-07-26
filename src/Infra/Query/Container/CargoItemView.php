<?php

declare(strict_types=1);

namespace Infra\Query\Container;

final readonly class CargoItemView
{
    public function __construct(
        public string $productId,
        public string $productName,
        public float $quantity,
        public float $weight,
    ) {
    }
}
