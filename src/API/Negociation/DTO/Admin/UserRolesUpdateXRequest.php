<?php

/**
 * User Roles Update Request Message.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation\DTO\Admin;

/**
 * The roles a user should hold from now on — the whole set, not a delta.
 *
 * @see UserRolesUpdateXRequestFactory What builds this from a request body.
 * @see RolePermissionsUpdateXRequest The sibling replace-the-whole-set message.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class UserRolesUpdateXRequest
{
    /**
     * @param  list<string>  $roleIds  Base62 ids of the roles the user ends up with.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public array $roleIds = [],
    ) {
    }
}
