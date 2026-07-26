<?php

declare(strict_types=1);

namespace App\Commands\Account;

use App\Context\UserContext;

/**
 * Self-service profile update. The target is always `$context->id` — see
 * {@see ChangePasswordCommand} for why no `userId` is accepted.
 */
final readonly class UpdateAccountCommand
{
    public function __construct(
        public UserContext $context,
        public string $name,
        public string $email,
    ) {
    }
}
