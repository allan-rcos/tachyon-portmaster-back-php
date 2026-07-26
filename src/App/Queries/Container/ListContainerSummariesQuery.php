<?php

declare(strict_types=1);

namespace App\Queries\Container;

use App\Context\UserContext;

final readonly class ListContainerSummariesQuery
{
    public function __construct(
        public UserContext $context,
        public ?string $id = null,
        public ?string $cursor = null,
        public ?int $limit = null,
    ) {
    }
}
