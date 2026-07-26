<?php

declare(strict_types=1);

namespace API\Fbs\Auth;

use API\Fbs\Contracts\CoercesJson;
use API\Fbs\Contracts\IFbsProxy;
use API\Http\ContentKind;
use API\Http\RequestAttributes;
use Google\FlatBuffers\ByteBuffer;
use Google\FlatBuffers\FlatbufferBuilder;
use OpenSwoole\Core\Psr\Stream;
use Psr\Http\Message\StreamInterface;

/**
 * JSON/binary-aware proxy around the generated {@see LoginRequest} table.
 */
final class LoginRequestProxy extends LoginRequest implements IFbsProxy
{
    use CoercesJson;

    public function __construct(
        public ?string $email = null,
        public ?string $password = null,
    ) {
    }

    public function buildInto(FlatbufferBuilder $builder): int
    {
        $email    = $this->email !== null ? $builder->createString($this->email) : 0;
        $password = $this->password !== null ? $builder->createString($this->password) : 0;

        return LoginRequest::createLoginRequest($builder, $email, $password);
    }

    public function toBinary(): string
    {
        $builder = new FlatbufferBuilder(0);
        $builder->finish($this->buildInto($builder));

        return $builder->sizedByteArray();
    }

    public static function fromTable(LoginRequest $table): static
    {
        return new static(
            email: $table->getEmail(),
            password: $table->getPassword(),
        );
    }

    public static function fromBinary(string $binary): static
    {
        return static::fromTable(LoginRequest::getRootAsLoginRequest(ByteBuffer::wrap($binary)));
    }

    public static function jsonUnserialize(array $data): static
    {
        return new static(
            email: self::jsonNullableString($data, 'email'),
            password: self::jsonNullableString($data, 'password'),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'email'    => $this->email,
            'password' => $this->password,
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
