<?php

/**
 * User Update Request Message.
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
 * A user being edited by an administrator.
 *
 * @see UserUpdateXRequestFactory What builds this from a request body.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class UserUpdateXRequest
{
    /**
     * @param  ?string  $name  Display name.
     * @param  ?string  $email  Email address.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
    ) {
    }
}
