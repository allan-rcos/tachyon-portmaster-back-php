<?php

declare(strict_types=1);

namespace App\Queries\Container;

use App\Context\UserContext;

final readonly class ListContainersQuery
{
    public function __construct(
        public UserContext $context,
        public ?string $cursor = null,
        public ?int $limit = null,
        public ?string $search = null,
        public ?string $status = null,
        public ?string $statusIn = null,
    ) {
    }
}
