<?php

/**
 * Login Response Message.
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
 * The body of a freshly opened session.
 *
 * @see LoginXResponseFactory What renders this onto the wire.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class LoginXResponse
{
    /**
     * @param  ?string  $token  The signed access token.
     * @param  ?string  $tokenType  How the token is carried; always `cookie` here.
     * @param  ?UserX  $user  The authenticated principal.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ?string $token = null,
        public ?string $tokenType = null,
        public ?UserX $user = null,
    ) {
    }
}
