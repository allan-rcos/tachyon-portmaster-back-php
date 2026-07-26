<?php

namespace Domain\Models\Internal;

use Domain\Enums\ContainerStatus;
use Domain\Models\IContainer;

class Container implements IContainer
{
    public function __construct(
        public string $id {
            get => $this->id;
        },
        public string $code {
            get => $this->code;
        },
        public float $currentWeight {
            get => $this->currentWeight;
        },
        public float $maxCapacity {
            get => $this->maxCapacity;
        },
        public ContainerStatus $status {
            get => $this->status;
        },
    ) {
    }
}
