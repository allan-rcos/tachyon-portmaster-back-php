<?php

/**
 * Login Command.
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
 * Input for {@see \App\Services\ILoginUseCase}: the credentials to authenticate.
 *
 * Commands are plain DTOs — they carry the use case's input across the
 * controller boundary so the use case never sees an HTTP request or a proxy.
 *
 * Carries no {@see \App\Context\UserContext}, and could not: establishing who
 * the caller is *is* the operation. That also means the use case behind it has
 * no permission to declare — there is nobody yet to check one against.
 *
 * @see \App\Services\ILoginUseCase What consumes it.
 * @see SetupCommand The other unauthenticated command.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class LoginCommand
{
    /**
     * @param  string  $email  As typed; the lookup normalises the case.
     * @param  string  $password  Plaintext, verified against the stored hash and
     *                            never retained.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public string $email,
        public string $password,
    ) {
    }
}
