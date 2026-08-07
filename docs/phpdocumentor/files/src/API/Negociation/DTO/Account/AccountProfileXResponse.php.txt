<?php

/**
 * Account Profile Response Message.
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
 * The caller's own profile, roles included.
 *
 * @see AccountProfileXResponseFactory What renders this onto the wire.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class AccountProfileXResponse
{
    /**
     * @param  ?string  $id  Base62 identifier.
     * @param  ?string  $name  Display name.
     * @param  ?string  $email  Email address.
     * @param  list<RoleXResponse>  $roles  Every role the caller holds.
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
