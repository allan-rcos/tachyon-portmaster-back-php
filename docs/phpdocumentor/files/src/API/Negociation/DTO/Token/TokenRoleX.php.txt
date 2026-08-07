<?php

/**
 * Token Role Message.
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
 * A role as it is carried inside the access token.
 *
 * @see TokenUserXFactory What writes and reads it, as part of the claim.
 * @see TokenUserX The claim it belongs to.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class TokenRoleX
{
    /**
     * Nothing defaults here, unlike the HTTP messages: a claim is either fully
     * present or the token is not to be trusted.
     *
     * @param  string  $id  Base62 identifier.
     * @param  string  $name  Display name.
     * @param  list<string>  $permissions  The permission slugs it grants.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $permissions,
    ) {
    }
}
