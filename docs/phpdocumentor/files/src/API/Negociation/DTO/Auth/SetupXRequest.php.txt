<?php

/**
 * Setup Request Message.
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
 * The first operator, as presented to `POST /setup`.
 *
 * @see SetupXRequestFactory What builds this from a request body.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class SetupXRequest
{
    /**
     * Every field defaults, so a body that omits one still hydrates. What is
     * missing is the domain's to report, with every broken field at once —
     * never this constructor's.
     *
     * @param  ?string  $name  Display name.
     * @param  ?string  $email  Email address.
     * @param  ?string  $password  The password, in clear; hashed before it is stored.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public ?string $password = null,
    ) {
    }
}
