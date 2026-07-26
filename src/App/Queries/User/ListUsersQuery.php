<?php

declare(strict_types=1);

namespace App\Queries\User;

use App\Context\UserContext;

/**
 * `GET /users` uses offset pagination (page/limit), not the cursor scheme.
 */
final readonly class ListUsersQuery
{
    public function __construct(
        public UserContext $context,
        public ?int $page = null,
        public ?int $limit = null,
    ) {
    }
}
