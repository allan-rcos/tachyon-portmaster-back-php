<?php

declare(strict_types=1);

namespace API\Fbs\Admin;

use API\Fbs\Contracts\CoercesJson;
use API\Fbs\Contracts\IFbsProxy;
use API\Http\ContentKind;
use API\Http\RequestAttributes;
use Google\FlatBuffers\ByteBuffer;
use Google\FlatBuffers\FlatbufferBuilder;
use OpenSwoole\Core\Psr\Stream;
use Psr\Http\Message\StreamInterface;

/**
 * JSON/binary-aware proxy around the generated {@see RoleCreateRequest} table.
 */
final class RoleCreateRequestProxy extends RoleCreateRequest implements IFbsProxy
{
    use CoercesJson;

    /**
     * @param  list<string>  $permissions  Permission slugs.
     */
    public function __construct(
        public ?string $name = null,
        public array $permissions = [],
    ) {
    }

    public function buildInto(FlatbufferBuilder $builder): int
    {
        // A [string] vector holds offsets, so each slug must be written first.
        $permissions = RoleCreateRequest::createPermissionsVector(
            $builder,
            array_map(static fn (string $slug): int => $builder->createString($slug), $this->permissions),
        );
        $name = $this->name !== null ? $builder->createString($this->name) : 0;

        return RoleCreateRequest::createRoleCreateRequest($builder, $name, $permissions);
    }

    public function toBinary(): string
    {
        $builder = new FlatbufferBuilder(0);
        $builder->finish($this->buildInto($builder));

        return $builder->sizedByteArray();
    }

    public static function fromTable(RoleCreateRequest $table): static
    {
        $permissions = [];
        for ($i = 0, $n = $table->getPermissionsLength(); $i < $n; $i++) {
            $permissions[] = (string) $table->getPermissions($i);
        }

        return new static(
            name: $table->getName(),
            permissions: $permissions,
        );
    }

    public static function fromBinary(string $binary): static
    {
        return static::fromTable(RoleCreateRequest::getRootAsRoleCreateRequest(ByteBuffer::wrap($binary)));
    }

    public static function jsonUnserialize(array $data): static
    {
        return new static(
            name: self::jsonNullableString($data, 'name'),
            permissions: self::jsonStringList($data, 'permissions'),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'name'        => $this->name,
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
