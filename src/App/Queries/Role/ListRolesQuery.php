<?php

declare(strict_types=1);

namespace App\Queries\Role;

use App\Context\UserContext;

final readonly class ListRolesQuery
{
    public function __construct(
        public UserContext $context,
        public ?string $cursor = null,
        public ?int $limit = null,
        public ?string $search = null,
    ) {
    }
}
