<?php

declare(strict_types=1);

namespace App\Commands\Account;

use App\Context\UserContext;

/**
 * Self-service password change.
 *
 * There is deliberately **no** `userId`: the target is always `$context->id`.
 * Carrying one would let a caller aim the change at another account, and no
 * amount of checking downstream is as safe as not accepting the parameter.
 * Changing someone else's password is a different operation with its own
 * permission — see {@see \App\Commands\User\ResetUserPasswordCommand}.
 */
final readonly class ChangePasswordCommand
{
    public function __construct(
        public UserContext $context,
        public string $currentPassword,
        public string $newPassword,
    ) {
    }
}
