<?php

declare(strict_types=1);

namespace Infra\Query\Product;

use Domain\Enums\RiskClass;

/**
 * A single product read record. Read-side DTO — built only by the product DQLs,
 * mapped to a response proxy by the controller.
 */
final readonly class ProductViewItem
{
    public function __construct(
        public string $id,
        public string $name,
        public float $density,
        public RiskClass $riskClass,
    ) {
    }
}
