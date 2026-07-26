<?php

declare(strict_types=1);

namespace App\Queries\Role;

use App\Context\UserContext;

/**
 * Reads one role by id.
 */
final readonly class GetRoleQuery
{
    public function __construct(
        public UserContext $context,
        public string $id,
    ) {
    }
}
