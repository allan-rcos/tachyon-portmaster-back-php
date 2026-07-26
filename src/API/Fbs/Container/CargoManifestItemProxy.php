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
 * JSON/binary-aware proxy around the generated {@see CargoManifestItem} table.
 */
final class CargoManifestItemProxy extends CargoManifestItem implements IFbsProxy
{
    use CoercesJson;

    public function __construct(
        public ?string $productId = null,
        public ?string $productName = null,
        public float $quantity = 0.0,
        public float $weight = 0.0,
    ) {
    }

    public function buildInto(FlatbufferBuilder $builder): int
    {
        $productId = $this->productId !== null ? $builder->createString($this->productId) : 0;
        $productName = $this->productName !== null ? $builder->createString($this->productName) : 0;

        return CargoManifestItem::createCargoManifestItem($builder, $productId, $productName, $this->quantity, $this->weight);
    }

    public function toBinary(): string
    {
        $builder = new FlatbufferBuilder(0);
        $builder->finish($this->buildInto($builder));

        return $builder->sizedByteArray();
    }

    public static function fromTable(CargoManifestItem $table): static
    {
        return new static(
            productId: $table->getProductId(),
            productName: $table->getProductName(),
            quantity: $table->getQuantity(),
            weight: $table->getWeight(),
        );
    }

    public static function fromBinary(string $binary): static
    {
        return static::fromTable(CargoManifestItem::getRootAsCargoManifestItem(ByteBuffer::wrap($binary)));
    }

    public static function jsonUnserialize(array $data): static
    {
        return new static(
            productId: self::jsonNullableString($data, 'product_id'),
            productName: self::jsonNullableString($data, 'product_name'),
            quantity: self::jsonFloat($data, 'quantity'),
            weight: self::jsonFloat($data, 'weight'),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'product_id'   => $this->productId,
            'product_name' => $this->productName,
            'quantity'     => $this->quantity,
            'weight'       => $this->weight,
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
