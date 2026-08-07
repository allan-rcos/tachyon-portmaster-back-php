<?php

/**
 * Role Response Message.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation\DTO\Account;

/**
 * A role as it travels inside a profile or a role listing.
 *
 * @see RoleXResponseFactory What renders this onto the wire.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class RoleXResponse
{
    /**
     * @param  ?string  $id  Base62 identifier.
     * @param  ?string  $name  Display name.
     * @param  int  $userCount  How many users hold the role.
     * @param  list<string>  $permissions  The permission slugs it grants.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ?string $id = null,
        public ?string $name = null,
        public int $userCount = 0,
        public array $permissions = [],
    ) {
    }
}
