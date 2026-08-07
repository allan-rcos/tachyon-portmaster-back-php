<?php

/**
 * Login Request Message.
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
 * The credentials a caller presents to open a session.
 *
 * Data and nothing else: which wire format it arrived in is
 * {@see LoginXRequestFactory}'s business, and whether the credentials are any
 * good is the domain's.
 *
 * @see LoginXRequestFactory What builds this from a request body.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class LoginXRequest
{
    /**
     * Every field defaults, so a body that omits one still hydrates. What is
     * missing is the domain's to report, with every broken field at once —
     * never this constructor's.
     *
     * @param  ?string  $email  Email address.
     * @param  ?string  $password  The password, in clear; hashed before it is stored.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ?string $email = null,
        public ?string $password = null,
    ) {
    }
}
