<?php

/**
 * Token User Proxy.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Fbs\Token;

use Google\FlatBuffers\ByteBuffer;
use Google\FlatBuffers\FlatbufferBuilder;

/**
 * The serialized principal that travels as the JWT's single payload claim.
 *
 * Instead of scattering `sub`, `name`, `email` and a `perms` array across loose
 * claims, the whole principal is one FlatBuffer, base64'd into a single claim.
 *
 * What this buys is **opacity and structure**: the payload keeps the role →
 * permission grouping (a flat `perms` list cannot say *which* role granted
 * what), and the cookie no longer reads as a plain list of the caller's
 * privileges to anyone who pastes it into a JWT decoder.
 *
 * What it does **not** buy is size. At this scale FlatBuffers loses to JSON —
 * measured at ~268 bytes raw / ~360 base64 against ~121 bytes of equivalent
 * JSON — because vtable and alignment overhead dominate a payload of a few short
 * strings. The format is worth it for the two properties above, not for
 * compactness; if the token ever needs to shrink, that is an argument for
 * trimming what travels in it, not for a different encoding.
 *
 * See {@see TokenRoleProxy} for why this is not an `IFbsProxy`.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class TokenUserProxy
{
    /**
     * @param  list<TokenRoleProxy>  $roles
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

    /**
     * {@inheritDoc}
     *
     * @return string A finished, size-prefixed buffer.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function toBinary(): string
    {
        $builder = new FlatbufferBuilder(0);

        // Nested tables and strings must be built before the parent table.
        $roles = TokenUser::createRolesVector(
            $builder,
            array_map(static fn (TokenRoleProxy $role): int => $role->buildInto($builder), $this->roles),
        );
        $id = $builder->createString($this->id);
        $name = $builder->createString($this->name);
        $email = $builder->createString($this->email);

        $builder->finish(TokenUser::createTokenUser($builder, $id, $name, $email, $roles));

        return $builder->sizedByteArray();
    }

    /**
     * {@inheritDoc}
     *
     * @param  string  $binary  A buffer produced against the same schema.
     * @return static The hydrated proxy.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function fromBinary(string $binary): self
    {
        $table = TokenUser::getRootAsTokenUser(ByteBuffer::wrap($binary));

        $roles = [];
        for ($i = 0, $n = $table->getRolesLength(); $i < $n; $i++) {
            $role = $table->getRoles($i);
            if ($role instanceof TokenRole) {
                $roles[] = TokenRoleProxy::fromTable($role);
            }
        }

        return new self(
            id: (string) $table->getId(),
            name: (string) $table->getName(),
            email: (string) $table->getEmail(),
            roles: $roles,
        );
    }
}
