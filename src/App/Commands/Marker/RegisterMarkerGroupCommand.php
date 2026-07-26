<?php

declare(strict_types=1);

namespace App\Commands\Marker;

/**
 * Declares one marker group the application layer needs.
 *
 * Like {@see \App\Commands\Permission\RegisterPermissionCommand} it carries no
 * {@see \App\Context\UserContext}: it runs at WorkerStart, before any request
 * exists, so there is no caller to authorize.
 */
final readonly class RegisterMarkerGroupCommand
{
    public function __construct(
        public string $slug,
    ) {
    }
}
