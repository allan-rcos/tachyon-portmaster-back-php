<?php

/**
 * Account Update Request Message.
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
 * A caller editing their own profile.
 *
 * @see AccountUpdateXRequestFactory What builds this from a request body.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class AccountUpdateXRequest
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
