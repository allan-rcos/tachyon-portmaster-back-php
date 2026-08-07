<?php

/**
 * Token User Message.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation\DTO\Token;

/**
 * The principal carried inside the access token.
 *
 * @see TokenUserXFactory What writes and reads it.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class TokenUserX
{
    /**
     * @param  string  $id  Base62 identifier.
     * @param  string  $name  Display name.
     * @param  string  $email  Email address.
     * @param  list<TokenRoleX>  $roles  Every role the principal held when the
     *                                   token was signed.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public array $roles,
    ) {
    }
}
