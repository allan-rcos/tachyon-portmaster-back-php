<?php

/**
 * User Admin Response Message.
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

use API\Negociation\DTO\Account\RoleXResponse;

/**
 * A user as the administration screens see them, roles included.
 *
 * @see UserAdminXResponseFactory What renders this onto the wire.
 * @see UserListXResponse Where it is nested.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class UserAdminXResponse
{
    /**
     * @param  ?string  $id  Base62 identifier.
     * @param  ?string  $name  Display name.
     * @param  ?string  $email  Email address.
     * @param  list<RoleXResponse>  $roles  Every role the user holds.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ?string $id = null,
        public ?string $name = null,
        public ?string $email = null,
        public array $roles = [],
    ) {
    }
}
