<?php

/**
 * Update Role Permissions Command.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App\Commands\Role;

use App\Context\UserContext;


/**
 * Replaces the set of permissions a role grants.
 *
 * Follows the command shape documented on
 * {@see \App\Commands\Product\CreateProductCommand}.
 *
 * A replacement, not an addition: slugs left out are revoked, so an empty list
 * strips the role of everything. Every user holding the role is affected on
 * their next request.
 *
 * @see \App\Services\IUpdateRolePermissionsUseCase What consumes it.
 * @see CreateRoleCommand Where the set is first established.
 * @see \App\Commands\Product\CreateProductCommand The command shape.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class UpdateRolePermissionsCommand
{
    /**
     * @param  UserContext  $context  The caller.
     * @param  string  $id  Base62 id of the role.
     * @param  list<string>  $permissions  The slugs the role should grant
     *                                     afterwards — the whole set, not the
     *                                     additions.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public UserContext $context,
        public string $id,
        public array $permissions,
    ) {
    }
}
