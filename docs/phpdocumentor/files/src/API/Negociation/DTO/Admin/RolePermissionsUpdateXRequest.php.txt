<?php

/**
 * Role Permissions Update Request Message.
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
 * The permissions a role should grant from now on — the whole set, not a delta.
 *
 * @see RolePermissionsUpdateXRequestFactory What builds this from a request body.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class RolePermissionsUpdateXRequest
{
    /**
     * @param  list<string>  $permissions  Permission slugs the role ends up with.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public array $permissions = [],
    ) {
    }
}
