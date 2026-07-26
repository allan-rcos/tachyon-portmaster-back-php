<?php

declare(strict_types=1);

namespace API\Fbs\Manifest;

use API\Fbs\Contracts\CoercesJson;
use API\Fbs\Contracts\IFbsProxy;
use API\Http\ContentKind;
use API\Http\RequestAttributes;
use Google\FlatBuffers\ByteBuffer;
use Google\FlatBuffers\FlatbufferBuilder;
use OpenSwoole\Core\Psr\Stream;
use Psr\Http\Message\StreamInterface;

/**
 * JSON/binary-aware proxy around the generated {@see UnloadItemRequest} table.
 */
final class UnloadItemRequestProxy extends UnloadItemRequest implements IFbsProxy
{
    use CoercesJson;

    public function __construct(
        public ?string $containerId = null,
        public ?string $productId = null,
        public float $quantity = 0.0,
    ) {
    }

    public function buildInto(FlatbufferBuilder $builder): int
    {
        $productId = $this->productId !== null ? $builder->createString($this->productId) : 0;
        $containerId = $this->containerId !== null ? $builder->createString($this->containerId) : 0;
        return UnloadItemRequest::createUnloadItemRequest($builder, $containerId, $productId, $this->quantity);
    }

    public function toBinary(): string
    {
        $builder = new FlatbufferBuilder(0);
        $builder->finish($this->buildInto($builder));

        return $builder->sizedByteArray();
    }

    public static function fromTable(UnloadItemRequest $table): static
    {
        return new static(
            containerId: $table->getContainerId(),
            productId: $table->getProductId(),
            quantity: $table->getQuantity(),
        );
    }

    public static function fromBinary(string $binary): static
    {
        return static::fromTable(UnloadItemRequest::getRootAsUnloadItemRequest(ByteBuffer::wrap($binary)));
    }

    public static function jsonUnserialize(array $data): static
    {
        return new static(
            containerId: self::jsonNullableString($data, 'container_id'),
            productId: self::jsonNullableString($data, 'product_id'),
            quantity: self::jsonFloat($data, 'quantity'),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'container_id' => $this->containerId,
            'product_id'   => $this->productId,
            'quantity'     => $this->quantity,
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
