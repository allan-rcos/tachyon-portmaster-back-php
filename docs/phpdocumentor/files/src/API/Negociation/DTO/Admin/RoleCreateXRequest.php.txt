<?php

/**
 * Role Create Request Message.
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
 * A role being created, with the permissions it starts out granting.
 *
 * @see RoleCreateXRequestFactory What builds this from a request body.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class RoleCreateXRequest
{
    /**
     * @param  ?string  $name  Display name.
     * @param  list<string>  $permissions  Permission slugs to grant.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ?string $name = null,
        public array $permissions = [],
    ) {
    }
}
