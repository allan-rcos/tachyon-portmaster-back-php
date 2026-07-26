<?php

declare(strict_types=1);

namespace App\Commands\Permission;

/**
 * Declares one permission the application layer needs.
 *
 * Unlike every other command this one carries no {@see \App\Context\UserContext}:
 * it runs at WorkerStart, from a use case's constructor, before any request
 * exists — there is no caller to authorize.
 */
final readonly class RegisterPermissionCommand
{
    public function __construct(
        public string $slug,
    ) {
    }
}
