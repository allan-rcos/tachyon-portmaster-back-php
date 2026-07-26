<?php

declare(strict_types=1);

namespace App\Queries\User;

use App\Context\UserContext;

/**
 * Reads another user's profile — the admin operation, guarded by `user:get`.
 *
 * Self-service reads go through {@see \App\Queries\Account\GetAccountQuery},
 * which takes no id at all. Keeping the two apart is what lets the admin path
 * require a permission while the account path only requires being logged in.
 */
final readonly class GetUserQuery
{
    public function __construct(
        public UserContext $context,
        public string $userId,
    ) {
    }
}
