<?php

declare(strict_types=1);

namespace App\Commands\User;

use App\Context\UserContext;

/**
 * Removes one user by id.
 */
final readonly class DeleteUserCommand
{
    public function __construct(
        public UserContext $context,
        public string $id,
    ) {
    }
}
