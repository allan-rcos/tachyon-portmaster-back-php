<?php

/**
 * Setup Command.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App\Commands;

/**
 * Bootstraps the very first user of a deployment.
 *
 * Carries no {@see \App\Context\UserContext}, and could not: it runs when there
 * is nobody to be the caller. What replaces authorization here is the state
 * check — the command is only honoured while the system has no users at all.
 *
 * @see \App\Services\ISetupUseCase What consumes it, and where that state check lives.
 * @see LoginCommand The other unauthenticated command.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class SetupCommand
{
    /**
     * @param  string  $name  Display name of the first user.
     * @param  string  $email  Their address.
     * @param  string  $password  Plaintext; the domain hashes it.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {
    }
}
