<?php

namespace Domain\Models;

use Domain\Enums\ContainerStatus;

interface IContainer
{
    public string $id {
        get;
    }

    public string $code {
        get;
    }

    public float $currentWeight {
        get;
    }

    public float $maxCapacity {
        get;
    }

    public ContainerStatus $status {
        get;
    }
}
