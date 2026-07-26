<?php

declare(strict_types=1);

namespace API\Fbs\Container;

use API\Fbs\Contracts\CoercesJson;
use API\Fbs\Contracts\IFbsProxy;
use API\Http\ContentKind;
use API\Http\RequestAttributes;
use Google\FlatBuffers\ByteBuffer;
use Google\FlatBuffers\FlatbufferBuilder;
use OpenSwoole\Core\Psr\Stream;
use Psr\Http\Message\StreamInterface;

/**
 * JSON/binary-aware proxy around the generated {@see ContainerCreateRequest} table.
 */
final class ContainerCreateRequestProxy extends ContainerCreateRequest implements IFbsProxy
{
    use CoercesJson;

    public function __construct(
        public ?string $code = null,
        public float $maxCapacity = 0.0,
    ) {
    }

    public function buildInto(FlatbufferBuilder $builder): int
    {
        $code = $this->code !== null ? $builder->createString($this->code) : 0;

        return ContainerCreateRequest::createContainerCreateRequest($builder, $code, $this->maxCapacity);
    }

    public function toBinary(): string
    {
        $builder = new FlatbufferBuilder(0);
        $builder->finish($this->buildInto($builder));

        return $builder->sizedByteArray();
    }

    public static function fromTable(ContainerCreateRequest $table): static
    {
        return new static(
            code: $table->getCode(),
            maxCapacity: $table->getMaxCapacity(),
        );
    }

    public static function fromBinary(string $binary): static
    {
        return static::fromTable(ContainerCreateRequest::getRootAsContainerCreateRequest(ByteBuffer::wrap($binary)));
    }

    public static function jsonUnserialize(array $data): static
    {
        return new static(
            code: self::jsonNullableString($data, 'code'),
            maxCapacity: self::jsonFloat($data, 'max_capacity'),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'code'         => $this->code,
            'max_capacity' => $this->maxCapacity,
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
