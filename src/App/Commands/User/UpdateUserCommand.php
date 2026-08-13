<?php

/**
 * Update User Command.
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
 * Changes another user's profile.
 *
 * Follows the command shape documented on
 * {@see \App\Commands\Product\CreateProductCommand}.
 *
 * The administrative counterpart of
 * {@see \App\Commands\Account\UpdateAccountCommand}, which is why this one
 * carries an `$id` and that one does not. Profile fields only: roles move
 * through {@see UpdateUserRolesCommand}, the password through
 * {@see ResetUserPasswordCommand}.
 *
 * @see \App\Services\IUpdateUserUseCase What consumes it.
 * @see \App\Commands\Account\UpdateAccountCommand The self-service counterpart.
 * @see \App\Commands\Product\CreateProductCommand The command shape.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class UpdateUserCommand
{
    /**
     * @param  UserContext  $context  The caller, who is not necessarily the
     *                                target.
     * @param  string  $id  Base62 id of the user to change.
     * @param  string  $name  Their new display name.
     * @param  string  $email  Their new address.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public UserContext $context,
        public string $id,
        public string $name,
        public string $email,
    ) {
    }
}
