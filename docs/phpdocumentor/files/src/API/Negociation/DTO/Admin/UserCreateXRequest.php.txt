<?php

/**
 * User Create Request Message.
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
 * A user being created by an administrator.
 *
 * @see UserCreateXRequestFactory What builds this from a request body.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class UserCreateXRequest
{
    /**
     * @param  ?string  $name  Display name.
     * @param  ?string  $email  Email address.
     * @param  ?string  $initialPassword  The password to start with, in clear.
     * @param  list<string>  $roleIds  Base62 ids of the roles to grant.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public ?string $initialPassword = null,
        public array $roleIds = [],
    ) {
    }
}
