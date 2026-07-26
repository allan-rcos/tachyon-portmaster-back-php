<?php

declare(strict_types=1);

namespace API\Fbs\Token;

use Google\FlatBuffers\FlatbufferBuilder;

/**
 * Builder/reader for the {@see TokenRole} claim table.
 *
 * Deliberately **not** an {@see \API\Fbs\Contracts\IFbsProxy}: that contract is
 * about HTTP content negotiation (json vs binary body), and a token claim is
 * never an HTTP body. It is always binary, always nested inside
 * {@see TokenUserProxy}, so it only needs to build and read.
 */
final readonly class TokenRoleProxy
{
    /**
     * @param  list<string>  $permissions  Permission slugs granted by the role.
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $permissions,
    ) {
    }

    public function buildInto(FlatbufferBuilder $builder): int
    {
        // Offsets for every string must exist before the table is started.
        $permissions = TokenRole::createPermissionsVector(
            $builder,
            array_map(static fn (string $slug): int => $builder->createString($slug), $this->permissions),
        );
        $id = $builder->createString($this->id);
        $name = $builder->createString($this->name);

        return TokenRole::createTokenRole($builder, $id, $name, $permissions);
    }

    public static function fromTable(TokenRole $table): self
    {
        $permissions = [];
        for ($i = 0, $n = $table->getPermissionsLength(); $i < $n; $i++) {
            $permissions[] = (string) $table->getPermissions($i);
        }

        return new self(
            id: (string) $table->getId(),
            name: (string) $table->getName(),
            permissions: $permissions,
        );
    }
}
