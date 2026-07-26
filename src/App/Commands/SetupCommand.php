<?php

declare(strict_types=1);

namespace App\Commands;

/**
 * Bootstraps the very first user of a deployment.
 *
 * Carries no {@see \App\Context\UserContext}, and could not: it runs when there
 * is nobody to be the caller. What replaces authorization here is the state
 * check — the command is only honoured while the system has no users at all.
 */
final readonly class SetupCommand
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {
    }
}
