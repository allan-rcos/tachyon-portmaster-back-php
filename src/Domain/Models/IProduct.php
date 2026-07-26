<?php

namespace Domain\Models;

use Domain\Enums\RiskClass;

interface IProduct
{
    public string $id {
        get;
    }

    public string $name {
        get;
    }

    public float $density {
        get;
    }

    public RiskClass $riskClass {
        get;
    }
}
