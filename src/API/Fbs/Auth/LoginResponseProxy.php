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
 * JSON/binary-aware proxy around the generated {@see LoginResponse} table.
 */
final class LoginResponseProxy extends LoginResponse implements IFbsProxy
{
    use CoercesJson;

    public function __construct(
        public ?string $token = null,
        public ?string $tokenType = null,
        public ?UserProxy $user = null,
    ) {
    }

    public function buildInto(FlatbufferBuilder $builder): int
    {
        $user      = $this->user?->buildInto($builder) ?? 0;
        $token     = $this->token !== null ? $builder->createString($this->token) : 0;
        $tokenType = $this->tokenType !== null ? $builder->createString($this->tokenType) : 0;

        return LoginResponse::createLoginResponse($builder, $token, $tokenType, $user);
    }

    public function toBinary(): string
    {
        $builder = new FlatbufferBuilder(0);
        $builder->finish($this->buildInto($builder));

        return $builder->sizedByteArray();
    }

    public static function fromTable(LoginResponse $table): static
    {
        $user = $table->getUser();

        return new static(
            token: $table->getToken(),
            tokenType: $table->getTokenType(),
            user: $user instanceof User ? UserProxy::fromTable($user) : null,
        );
    }

    public static function fromBinary(string $binary): static
    {
        return static::fromTable(LoginResponse::getRootAsLoginResponse(ByteBuffer::wrap($binary)));
    }

    public static function jsonUnserialize(array $data): static
    {
        return new static(
            token: self::jsonNullableString($data, 'token'),
            tokenType: self::jsonNullableString($data, 'token_type'),
            user: ($obj = self::jsonObject($data, 'user')) !== null
                ? UserProxy::jsonUnserialize($obj)
                : null,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'token'      => $this->token,
            'token_type' => $this->tokenType,
            'user'       => $this->user?->jsonSerialize(),
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
