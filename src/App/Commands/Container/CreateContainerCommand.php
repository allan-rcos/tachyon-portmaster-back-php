<?php

declare(strict_types=1);

namespace App\Commands\Container;

use App\Context\UserContext;

final readonly class CreateContainerCommand
{
    public function __construct(
        public UserContext $context,
        public string $code,
        public float $maxCapacity,
    ) {
    }
}
