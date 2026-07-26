<?php

declare(strict_types=1);

namespace API\Fbs\Account;

use API\Fbs\Contracts\CoercesJson;
use API\Fbs\Contracts\IFbsProxy;
use API\Http\ContentKind;
use API\Http\RequestAttributes;
use Google\FlatBuffers\ByteBuffer;
use Google\FlatBuffers\FlatbufferBuilder;
use OpenSwoole\Core\Psr\Stream;
use Psr\Http\Message\StreamInterface;

/**
 * JSON/binary-aware proxy around the generated {@see RoleResponse} table.
 */
final class RoleResponseProxy extends RoleResponse implements IFbsProxy
{
    use CoercesJson;

    /**
     * @param  list<string>  $permissions  Permission slugs.
     */
    public function __construct(
        public ?string $id = null,
        public ?string $name = null,
        public int $userCount = 0,
        public array $permissions = [],
    ) {
    }

    public function buildInto(FlatbufferBuilder $builder): int
    {
        $id = $this->id !== null ? $builder->createString($this->id) : 0;
        // A [string] vector holds offsets, so each slug must be written first.
        $permissions = RoleResponse::createPermissionsVector(
            $builder,
            array_map(static fn (string $slug): int => $builder->createString($slug), $this->permissions),
        );
        $name = $this->name !== null ? $builder->createString($this->name) : 0;

        return RoleResponse::createRoleResponse($builder, $id, $name, $this->userCount, $permissions);
    }

    public function toBinary(): string
    {
        $builder = new FlatbufferBuilder(0);
        $builder->finish($this->buildInto($builder));

        return $builder->sizedByteArray();
    }

    public static function fromTable(RoleResponse $table): static
    {
        $permissions = [];
        for ($i = 0, $n = $table->getPermissionsLength(); $i < $n; $i++) {
            $permissions[] = (string) $table->getPermissions($i);
        }

        return new static(
            id: $table->getId(),
            name: $table->getName(),
            userCount: $table->getUserCount(),
            permissions: $permissions,
        );
    }

    public static function fromBinary(string $binary): static
    {
        return static::fromTable(RoleResponse::getRootAsRoleResponse(ByteBuffer::wrap($binary)));
    }

    public static function jsonUnserialize(array $data): static
    {
        return new static(
            id: self::jsonNullableString($data, 'id'),
            name: self::jsonNullableString($data, 'name'),
            userCount: self::jsonInt($data, 'user_count'),
            permissions: self::jsonStringList($data, 'permissions'),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'user_count'  => $this->userCount,
            'permissions' => $this->permissions,
        ];
    }

    public static function fromStream(StreamInterface $body): static
    {
        $raw = (string) $body;

        if (RequestAttributes::RequestContentKind->read() === ContentKind::Json) {
            $decoded = json_decode($raw, true);

            return static::jsonUnserialize(is_array($decoded) ? $decoded : []);
        }

        return static::fromBinary($raw);
    }

    public function toStream(): StreamInterface
    {
        $payload = RequestAttributes::ResponseContentKind->read() === ContentKind::Json
            ? (string) json_encode($this)
            : $this->toBinary();

        return Stream::streamFor($payload);
    }
}
