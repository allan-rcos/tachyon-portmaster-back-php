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
 * JSON/binary-aware proxy around the generated {@see UserCreateRequest} table.
 */
final class UserCreateRequestProxy extends UserCreateRequest implements IFbsProxy
{
    use CoercesJson;

    /**
     * @param  list<string>  $roleIds
     */
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public ?string $initialPassword = null,
        public array $roleIds = [],
    ) {
    }

    public function buildInto(FlatbufferBuilder $builder): int
    {
        $roleIdOffsets = array_map(fn (string $id): int => $builder->createString($id), $this->roleIds);
        $roleIds  = UserCreateRequest::createRoleIdsVector($builder, $roleIdOffsets);
        $name     = $this->name !== null ? $builder->createString($this->name) : 0;
        $email    = $this->email !== null ? $builder->createString($this->email) : 0;
        $password = $this->initialPassword !== null ? $builder->createString($this->initialPassword) : 0;

        return UserCreateRequest::createUserCreateRequest($builder, $name, $email, $password, $roleIds);
    }

    public function toBinary(): string
    {
        $builder = new FlatbufferBuilder(0);
        $builder->finish($this->buildInto($builder));

        return $builder->sizedByteArray();
    }

    public static function fromTable(UserCreateRequest $table): static
    {
        $roleIds = [];
        for ($i = 0, $n = $table->getRoleIdsLength(); $i < $n; $i++) {
            $roleIds[] = (string) $table->getRoleIds($i);
        }

        return new static(
            name: $table->getName(),
            email: $table->getEmail(),
            initialPassword: $table->getInitialPassword(),
            roleIds: $roleIds,
        );
    }

    public static function fromBinary(string $binary): static
    {
        return static::fromTable(UserCreateRequest::getRootAsUserCreateRequest(ByteBuffer::wrap($binary)));
    }

    public static function jsonUnserialize(array $data): static
    {
        return new static(
            name: self::jsonNullableString($data, 'name'),
            email: self::jsonNullableString($data, 'email'),
            initialPassword: self::jsonNullableString($data, 'initial_password'),
            roleIds: self::jsonStringList($data, 'role_ids'),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'name'             => $this->name,
            'email'            => $this->email,
            'initial_password' => $this->initialPassword,
            'role_ids'         => array_map('intval', $this->roleIds),
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
