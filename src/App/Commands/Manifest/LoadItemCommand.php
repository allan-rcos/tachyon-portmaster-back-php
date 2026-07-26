<?php

declare(strict_types=1);

namespace App\Commands\Manifest;

use App\Context\UserContext;

final readonly class LoadItemCommand
{
    public function __construct(
        public UserContext $context,
        public string $containerId,
        public string $productId,
        public float $quantity,
    ) {
    }
}
