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
 * JSON/binary-aware proxy around the generated {@see AccountUpdateRequest} table.
 */
final class AccountUpdateRequestProxy extends AccountUpdateRequest implements IFbsProxy
{
    use CoercesJson;

    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
    ) {
    }

    public function buildInto(FlatbufferBuilder $builder): int
    {
        $name  = $this->name !== null ? $builder->createString($this->name) : 0;
        $email = $this->email !== null ? $builder->createString($this->email) : 0;

        return AccountUpdateRequest::createAccountUpdateRequest($builder, $name, $email);
    }

    public function toBinary(): string
    {
        $builder = new FlatbufferBuilder(0);
        $builder->finish($this->buildInto($builder));

        return $builder->sizedByteArray();
    }

    public static function fromTable(AccountUpdateRequest $table): static
    {
        return new static(
            name: $table->getName(),
            email: $table->getEmail(),
        );
    }

    public static function fromBinary(string $binary): static
    {
        return static::fromTable(AccountUpdateRequest::getRootAsAccountUpdateRequest(ByteBuffer::wrap($binary)));
    }

    public static function jsonUnserialize(array $data): static
    {
        return new static(
            name: self::jsonNullableString($data, 'name'),
            email: self::jsonNullableString($data, 'email'),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'name'  => $this->name,
            'email' => $this->email,
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
