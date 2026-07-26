<?php

declare(strict_types=1);

namespace API\Fbs\Admin;

use API\Fbs\Account\RoleResponse;
use API\Fbs\Account\RoleResponseProxy;
use API\Fbs\Contracts\CoercesJson;
use API\Fbs\Contracts\IFbsProxy;
use API\Http\ContentKind;
use API\Http\RequestAttributes;
use Google\FlatBuffers\ByteBuffer;
use Google\FlatBuffers\FlatbufferBuilder;
use OpenSwoole\Core\Psr\Stream;
use Psr\Http\Message\StreamInterface;

/**
 * JSON/binary-aware proxy around the generated {@see UserAdminResponse} table.
 */
final class UserAdminResponseProxy extends UserAdminResponse implements IFbsProxy
{
    use CoercesJson;

    /**
     * @param  list<RoleResponseProxy>  $roles
     */
    public function __construct(
        public ?string $id = null,
        public ?string $name = null,
        public ?string $email = null,
        public array $roles = [],
    ) {
    }

    public function buildInto(FlatbufferBuilder $builder): int
    {
        $id = $this->id !== null ? $builder->createString($this->id) : 0;
        $roleOffsets = array_map(fn(RoleResponseProxy $role) => $role->buildInto($builder), $this->roles);
        $roles       = UserAdminResponse::createRolesVector($builder, $roleOffsets);
        $name        = $this->name !== null ? $builder->createString($this->name) : 0;
        $email       = $this->email !== null ? $builder->createString($this->email) : 0;

        return UserAdminResponse::createUserAdminResponse($builder, $id, $name, $email, $roles);
    }

    public function toBinary(): string
    {
        $builder = new FlatbufferBuilder(0);
        $builder->finish($this->buildInto($builder));

        return $builder->sizedByteArray();
    }

    public static function fromTable(UserAdminResponse $table): static
    {
        $roles = [];
        for ($i = 0, $n = $table->getRolesLength(); $i < $n; $i++) {
            $roles[] = RoleResponseProxy::fromTable($table->getRoles($i));
        }

        return new static(
            id: $table->getId(),
            name: $table->getName(),
            email: $table->getEmail(),
            roles: $roles,
        );
    }

    public static function fromBinary(string $binary): static
    {
        // Parse into a proxy instance so the overridden getRoles() below — which
        // fixes flatc's mis-namespaced nested getter — is used while reading.
        $buffer = ByteBuffer::wrap($binary);
        $proxy  = new static();
        $proxy->init($buffer->getInt($buffer->getPosition()) + $buffer->getPosition(), $buffer);

        return static::fromTable($proxy);
    }

    /**
     * Overrides the generated {@see UserAdminResponse::getRoles()}, which
     * instantiates an unqualified `RoleResponse` and therefore resolves to the
     * wrong (Admin) namespace on case-sensitive autoloaders.
     */
    /**
     * @param  int  $j
     */
    public function getRoles($j): ?RoleResponse
    {
        $offset = $this->__offset(10);
        if ($offset === 0) {
            return null;
        }

        /** @var int $vector */
        $vector = $this->__vector($offset);
        /** @var int $position */
        $position = $this->__indirect($vector + $j * 4);

        return (new RoleResponse())->init($position, $this->bb);
    }

    public static function jsonUnserialize(array $data): static
    {
        return new static(
            id: self::jsonNullableString($data, 'id'),
            name: self::jsonNullableString($data, 'name'),
            email: self::jsonNullableString($data, 'email'),
            roles: array_map(static fn (array $row): RoleResponseProxy => RoleResponseProxy::jsonUnserialize($row), self::jsonRows($data, 'roles')),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id'    => $this->id,
            'name'  => $this->name,
            'email' => $this->email,
            'roles' => array_map(fn(RoleResponseProxy $role) => $role->jsonSerialize(), $this->roles),
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
