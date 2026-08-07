<?php

/**
 * Token User Factory.
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

use API\Fbs\Token\TokenRole;
use API\Fbs\Token\TokenUser;
use Google\FlatBuffers\ByteBuffer;
use Google\FlatBuffers\FlatbufferBuilder;

/**
 * Writes and reads a {@see TokenUserX} as a FlatBuffer.
 *
 * Outside the negotiation interfaces on purpose: a token claim never travels as
 * an HTTP body, so there is no `Accept` to honour and no strategy to pick — it
 * is always binary, in both directions, inside a JWT. That also makes it the
 * only factory in the application that goes both ways, and the only one whose
 * methods are static: its caller,
 * {@see \API\Auth\Interno\FirebaseJwtTokenService}, holds no factory.
 *
 * The nested {@see TokenRoleX} has no factory of its own. It is written and
 * read here, privately, because nothing outside this claim ever needs a role on
 * its own — and a static that another class could call is exactly the surface
 * this pair should not have.
 *
 * @see \API\Auth\Interno\FirebaseJwtTokenService The only caller.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class TokenUserXFactory
{
    /**
     * The claim as a finished FlatBuffer, ready to be base64'd into the token.
     *
     * The buffer is positioned here rather than by whoever reads it, which is
     * where the negotiated path puts it: this claim has no strategy behind it,
     * only {@see \API\Auth\Interno\FirebaseJwtTokenService} asking for bytes.
     *
     * @param  TokenUserX  $user  The principal to encode.
     * @return ByteBuffer A finished, size-prefixed buffer, ready to read.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function toFlatbuffer(TokenUserX $user): ByteBuffer
    {
        $builder = new FlatbufferBuilder(0);

        // Nested tables and strings must be built before the parent table.
        $roles = TokenUser::createRolesVector(
            $builder,
            array_map(
                static fn (TokenRoleX $role): int => self::roleInto($builder, $role),
                $user->roles,
            ),
        );

        $id    = $builder->createString($user->id);
        $name  = $builder->createString($user->name);
        $email = $builder->createString($user->email);

        $builder->finish(TokenUser::createTokenUser($builder, $id, $name, $email, $roles));

        $buffer = ByteBuffer::wrap($builder->sizedByteArray());
        $buffer->setPosition(0);

        return $buffer;
    }

    /**
     * Reads the claim back out of a token's payload.
     *
     * @param  ByteBuffer  $buffer  A buffer produced by {@see toFlatbuffer()}.
     * @return TokenUserX The hydrated principal.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function fromFlatbuffer(ByteBuffer $buffer): TokenUserX
    {
        $buffer->setPosition(0);

        $table = TokenUser::getRootAsTokenUser($buffer);

        $roles = [];
        for ($i = 0, $n = $table->getRolesLength(); $i < $n; $i++) {
            $role = $table->getRoles($i);
            if ($role instanceof TokenRole) {
                $roles[] = self::roleFrom($role);
            }
        }

        return new TokenUserX(
            id: (string) $table->getId(),
            name: (string) $table->getName(),
            email: (string) $table->getEmail(),
            roles: $roles,
        );
    }

    /**
     * Appends one role to the claim's builder and returns its table offset.
     *
     * @param  FlatbufferBuilder  $builder  This claim's builder.
     * @param  TokenRoleX  $role  The role to append.
     * @return int The role table's offset within the builder.
     *
     * @copyright 2026 Tachyon
     */
    private static function roleInto(FlatbufferBuilder $builder, TokenRoleX $role): int
    {
        // Offsets for every string must exist before the table is started.
        $permissions = TokenRole::createPermissionsVector(
            $builder,
            array_map(static fn (string $slug): int => $builder->createString($slug), $role->permissions),
        );

        $id   = $builder->createString($role->id);
        $name = $builder->createString($role->name);

        return TokenRole::createTokenRole($builder, $id, $name, $permissions);
    }

    /**
     * Copies one generated role table into its DTO.
     *
     * @param  TokenRole  $table  The generated table to read.
     * @return TokenRoleX The hydrated role.
     *
     * @copyright 2026 Tachyon
     */
    private static function roleFrom(TokenRole $table): TokenRoleX
    {
        $permissions = [];
        for ($i = 0, $n = $table->getPermissionsLength(); $i < $n; $i++) {
            $permissions[] = (string) $table->getPermissions($i);
        }

        return new TokenRoleX(
            id: (string) $table->getId(),
            name: (string) $table->getName(),
            permissions: $permissions,
        );
    }
}
