<?php

/**
 * User List Response Message.
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
 * Every user, unpaginated — the administration listing.
 *
 * @see UserListXResponseFactory What renders this onto the wire.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class UserListXResponse
{
    /**
     * @param  list<UserAdminXResponse>  $data  The users.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public array $data = [],
    ) {
    }
}
