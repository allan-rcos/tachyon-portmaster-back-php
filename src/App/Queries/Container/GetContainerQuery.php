<?php

declare(strict_types=1);

namespace App\Queries\Container;

use App\Context\UserContext;

/**
 * Reads one container by id.
 */
final readonly class GetContainerQuery
{
    public function __construct(
        public UserContext $context,
        public string $id,
    ) {
    }
}
