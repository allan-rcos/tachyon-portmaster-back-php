<?php

declare(strict_types=1);

namespace App\Commands\Container;

use App\Context\UserContext;

final readonly class UpdateContainerCommand
{
    public function __construct(
        public UserContext $context,
        public string $id,
        public float $maxCapacity,
    ) {
    }
}
