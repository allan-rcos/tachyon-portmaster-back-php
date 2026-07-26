<?php

declare(strict_types=1);

namespace App\Queries\Product;

use App\Context\UserContext;

/**
 * Raw list parameters from the request. The cursor is opaque here — only the
 * DQL decodes it; everything above just passes it through.
 */
final readonly class ListProductsQuery
{
    public function __construct(
        public UserContext $context,
        public ?string $cursor = null,
        public ?int $limit = null,
        public ?string $search = null,
    ) {
    }
}
