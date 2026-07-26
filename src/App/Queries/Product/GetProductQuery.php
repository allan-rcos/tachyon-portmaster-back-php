<?php

declare(strict_types=1);

namespace App\Queries\Product;

use App\Context\UserContext;

/**
 * Reads one product by id.
 */
final readonly class GetProductQuery
{
    public function __construct(
        public UserContext $context,
        public string $id,
    ) {
    }
}
