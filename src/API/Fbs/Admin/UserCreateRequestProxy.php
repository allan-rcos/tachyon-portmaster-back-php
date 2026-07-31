<?php

/**
 * User Create Request Proxy.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

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
 *
 * @see \API\Fbs\Server\ProjectInfoProxy The proxy shape this follows.
 * @see \API\Fbs\Contracts\IFbsProxy The contract, and why none of this validates.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final class UserCreateRequestProxy extends UserCreateRequest implements IFbsProxy
{
    use CoercesJson;

    /**
     * @param  list<string>  $roleIds
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public ?string $initialPassword = null,
        public array $roleIds = [],
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @param  FlatbufferBuilder  $builder  The builder to append to.
     * @return int This table's offset within it.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function buildInto(FlatbufferBuilder $builder): int
    {
        $roleIdOffsets = array_map(fn (string $id): int => $builder->createString($id), $this->roleIds);
        $roleIds  = UserCreateRequest::createRoleIdsVector($builder, $roleIdOffsets);
        $name     = $this->name !== null ? $builder->createString($this->name) : 0;
        $email    = $this->email !== null ? $builder->createString($this->email) : 0;
        $password = $this->initialPassword !== null ? $builder->createString($this->initialPassword) : 0;

        return UserCreateRequest::createUserCreateRequest($builder, $name, $email, $password, $roleIds);
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
        $builder->finish($this->buildInto($builder));

        return $builder->sizedByteArray();
    }

    /**
     * Copies a generated table's fields into a proxy.
     *
     * See {@see \API\Fbs\Server\ProjectInfoProxy::fromTable()} for why this
     * sits outside {@see IFbsProxy}.
     *
     * @param  UserCreateRequest  $table  The generated table to read.
     * @return static The hydrated proxy.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
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
    public static function fromBinary(string $binary): static
    {
        return static::fromTable(UserCreateRequest::getRootAsUserCreateRequest(ByteBuffer::wrap($binary)));
    }

    /**
     * {@inheritDoc}
     *
     * @param  array<string, mixed>  $data  A `json_decode(..., true)` result.
     * @return static The hydrated proxy.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function jsonUnserialize(array $data): static
    {
        return new static(
            name: self::jsonNullableString($data, 'name'),
            email: self::jsonNullableString($data, 'email'),
            initialPassword: self::jsonNullableString($data, 'initial_password'),
            roleIds: self::jsonStringList($data, 'role_ids'),
        );
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, mixed> Ready for `json_encode()`.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function jsonSerialize(): array
    {
        return [
            'name'             => $this->name,
            'email'            => $this->email,
            'initial_password' => $this->initialPassword,
            'role_ids'         => array_map('intval', $this->roleIds),
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @param  StreamInterface  $body  The request body.
     * @return static The hydrated proxy; an empty one when the body does not
     *                parse.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function fromStream(StreamInterface $body): static
    {
        $raw = (string) $body;

        if (RequestAttributes::RequestContentKind->read() === ContentKind::Json) {
            $decoded = json_decode($raw, true);

            return static::jsonUnserialize(is_array($decoded) ? $decoded : []);
        }

        return static::fromBinary($raw);
    }

    /**
     * {@inheritDoc}
     *
     * @return StreamInterface The response body.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function toStream(): StreamInterface
    {
        $payload = RequestAttributes::ResponseContentKind->read() === ContentKind::Json
            ? (string) json_encode($this)
            : $this->toBinary();

        return Stream::streamFor($payload);
    }
}
