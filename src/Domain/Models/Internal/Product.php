<?php

namespace Domain\Models\Internal;

use Domain\Enums\RiskClass;
use Domain\Models\IProduct;

class Product implements IProduct
{
    public function __construct(
        public string $id {
            get => $this->id;
        },
        public string $name {
            get => $this->name;
        },
        public float $density {
            get => $this->density;
        },
        public RiskClass $riskClass {
            get => $this->riskClass;
        },
    ) {
    }
}
