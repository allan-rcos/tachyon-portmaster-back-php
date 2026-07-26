<?php

declare(strict_types=1);

namespace App\Queries\Account;

use App\Context\UserContext;

/**
 * Reads the **caller's own** profile.
 *
 * Carries no id: the subject is always `$context->id`. Reading someone else's
 * profile is a separate, permission-guarded operation
 * ({@see \App\Queries\User\GetUserQuery}).
 */
final readonly class GetAccountQuery
{
    public function __construct(
        public UserContext $context,
    ) {
    }
}
