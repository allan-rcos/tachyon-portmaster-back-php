<?php

/**
 * Update User Roles Command.
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
 * Replaces which roles a user holds.
 *
 * Follows the command shape documented on
 * {@see \App\Commands\Product\CreateProductCommand}.
 *
 * A replacement, not an addition: roles left out are removed, so an empty list
 * strips the user of every privilege. Separate from {@see UpdateUserCommand}
 * because changing what someone may do is a different privilege from correcting
 * their name.
 *
 * @see \App\Services\IUpdateUserRolesUseCase What consumes it.
 * @see UpdateUserCommand The profile-only counterpart.
 * @see \App\Commands\Product\CreateProductCommand The command shape.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class UpdateUserRolesCommand
{
    /**
     * @param  UserContext  $context  The caller.
     * @param  string  $id  Base62 id of the user.
     * @param  list<string>  $roleIds  Base62 role ids they should hold
     *                                 afterwards — the whole set, not the
     *                                 additions.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public UserContext $context,
        public string $id,
        public array $roleIds,
    ) {
    }
}
