<?php

declare(strict_types=1);

namespace App\Commands\Container;

use App\Context\UserContext;

/**
 * Dispatches one sealed container into transit.
 */
final readonly class DispatchContainerCommand
{
    public function __construct(
        public UserContext $context,
        public string $id,
    ) {
    }
}
