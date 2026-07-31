<?php

/**
 * Create User Command.
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
 * Creates a user, with their roles assigned in the same operation.
 *
 * Follows the command shape documented on
 * {@see \App\Commands\Product\CreateProductCommand}.
 *
 * Roles are named by id here, unlike a role's permissions which are named by
 * slug: a role is a stored record with an identity of its own, whereas a
 * permission is only ever its name.
 *
 * @see \App\Services\ICreateUserUseCase What consumes it.
 * @see UpdateUserRolesCommand How the assignment is changed afterwards.
 * @see \App\Commands\Product\CreateProductCommand The command shape.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class CreateUserCommand
{
    /**
     * @param  UserContext  $context  The caller.
     * @param  string  $name  Display name of the new user.
     * @param  string  $email  Their address; the unique index decides whether it
     *                         is already taken.
     * @param  string  $initialPassword  Plaintext; the domain hashes it and this
     *                                   layer never retains it.
     * @param  list<string>  $roleIds  Base62 role ids to assign; empty creates a
     *                                 user who can do nothing until granted a
     *                                 role.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public UserContext $context,
        public string $name,
        public string $email,
        public string $initialPassword,
        public array $roleIds,
    ) {
    }
}
