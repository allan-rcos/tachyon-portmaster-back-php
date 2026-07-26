<?php

declare(strict_types=1);

namespace App\Commands\Product;

use App\Context\UserContext;

use Domain\Enums\RiskClass;

final readonly class UpdateProductCommand
{
    public function __construct(
        public UserContext $context,
        public string $id,
        public string $name,
        public float $density,
        public RiskClass $riskClass,
    ) {
    }
}
