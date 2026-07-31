<?php

/**
 * Reset User Password Command.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App\Commands\User;

use App\Context\UserContext;

/**
 * Resets another user's password administratively.
 *
 * The counterpart of {@see \App\Commands\Account\ChangePasswordCommand}, and
 * different in the way that matters: no current password is required, because
 * the caller is not the account's owner and could not supply it. What replaces
 * that proof is the permission — which is why the two are separate operations
 * rather than one with an optional field.
 *
 * @see \App\Services\IResetUserPasswordUseCase What consumes it.
 * @see \App\Commands\Account\ChangePasswordCommand The self-service counterpart.
 * @see \App\Commands\Product\CreateProductCommand The command shape.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class ResetUserPasswordCommand
{
    /**
     * @param  UserContext  $context  The caller, who is not the target.
     * @param  string  $id  Base62 id of the user whose password to reset.
     * @param  string  $newPassword  Plaintext; the domain hashes it and this
     *                               layer never retains it.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public UserContext $context,
        public string $id,
        public string $newPassword,
    ) {
    }
}
