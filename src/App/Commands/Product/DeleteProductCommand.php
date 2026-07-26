<?php

declare(strict_types=1);

namespace App\Commands\Product;

use App\Context\UserContext;

/**
 * Removes one product by id.
 */
final readonly class DeleteProductCommand
{
    public function __construct(
        public UserContext $context,
        public string $id,
    ) {
    }
}
