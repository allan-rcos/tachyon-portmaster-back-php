<?php

declare(strict_types=1);

namespace App\Commands\Container;

use App\Context\UserContext;

/**
 * Seals one container, closing it for further loading.
 */
final readonly class SealContainerCommand
{
    public function __construct(
        public UserContext $context,
        public string $id,
    ) {
    }
}
