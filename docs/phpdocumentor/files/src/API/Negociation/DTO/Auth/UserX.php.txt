<?php

/**
 * User Message.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation\DTO\Auth;

/**
 * The authenticated principal, as it travels inside a session response.
 *
 * @see UserXFactory What renders this onto the wire.
 * @see LoginXResponse Where it is nested.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class UserX
{
    /**
     * @param  ?string  $id  Base62 identifier.
     * @param  ?string  $name  Display name.
     * @param  ?string  $email  Email address.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ?string $id = null,
        public ?string $name = null,
        public ?string $email = null,
    ) {
    }
}
