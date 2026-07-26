<?php

declare(strict_types=1);

namespace App\Commands\Container;

use App\Context\UserContext;

/**
 * Removes one container by id.
 */
final readonly class DeleteContainerCommand
{
    public function __construct(
        public UserContext $context,
        public string $id,
    ) {
    }
}
