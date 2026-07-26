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
 * JSON/binary-aware proxy around the generated {@see RolePermissionsUpdateRequest}
 * table.
 */
final class RolePermissionsUpdateRequestProxy extends RolePermissionsUpdateRequest implements IFbsProxy
{
    use CoercesJson;

    /**
     * @param  list<string>  $permissions  Permission slugs to set (full replacement).
     */
    public function __construct(
        public array $permissions = [],
    ) {
    }

    public function buildInto(FlatbufferBuilder $builder): int
    {
        // A [string] vector holds offsets, so each slug must be written first.
        $permissions = RolePermissionsUpdateRequest::createPermissionsVector(
            $builder,
            array_map(static fn (string $slug): int => $builder->createString($slug), $this->permissions),
        );

        return RolePermissionsUpdateRequest::createRolePermissionsUpdateRequest($builder, $permissions);
    }

    public function toBinary(): string
    {
        $builder = new FlatbufferBuilder(0);
        $builder->finish($this->buildInto($builder));

        return $builder->sizedByteArray();
    }

    public static function fromTable(RolePermissionsUpdateRequest $table): static
    {
        $permissions = [];
        for ($i = 0, $n = $table->getPermissionsLength(); $i < $n; $i++) {
            $permissions[] = (string) $table->getPermissions($i);
        }

        return new static(permissions: $permissions);
    }

    public static function fromBinary(string $binary): static
    {
        return static::fromTable(
            RolePermissionsUpdateRequest::getRootAsRolePermissionsUpdateRequest(ByteBuffer::wrap($binary)),
        );
    }

    public static function jsonUnserialize(array $data): static
    {
        return new static(permissions: self::jsonStringList($data, 'permissions'));
    }

    public function jsonSerialize(): array
    {
        return ['permissions' => $this->permissions];
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
