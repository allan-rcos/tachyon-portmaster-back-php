<?php

/**
 * Load Item Request Proxy.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

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
 * JSON/binary-aware proxy around the generated {@see LoadItemRequest} table.
 *
 * @see \API\Fbs\Server\ProjectInfoProxy The proxy shape this follows.
 * @see \API\Fbs\Contracts\IFbsProxy The contract, and why none of this validates.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final class LoadItemRequestProxy extends LoadItemRequest implements IFbsProxy
{
    use CoercesJson;

    /**
     * Every field defaults, so `new static()` is a valid empty message.
     *
     * @param  ?string  $containerId  Base62 id of the container.
     * @param  ?string  $productId  Base62 id of the product.
     * @param  float  $quantity  How many units.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ?string $containerId = null,
        public ?string $productId = null,
        public float $quantity = 0.0,
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
        $productId = $this->productId !== null ? $builder->createString($this->productId) : 0;
        $containerId = $this->containerId !== null ? $builder->createString($this->containerId) : 0;
        return LoadItemRequest::createLoadItemRequest($builder, $containerId, $productId, $this->quantity);
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
     * @param  LoadItemRequest  $table  The generated table to read.
     * @return static The hydrated proxy.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function fromTable(LoadItemRequest $table): static
    {
        return new static(
            containerId: $table->getContainerId(),
            productId: $table->getProductId(),
            quantity: $table->getQuantity(),
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
        return static::fromTable(LoadItemRequest::getRootAsLoadItemRequest(ByteBuffer::wrap($binary)));
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
            containerId: self::jsonNullableString($data, 'container_id'),
            productId: self::jsonNullableString($data, 'product_id'),
            quantity: self::jsonFloat($data, 'quantity'),
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
            'container_id' => $this->containerId,
            'product_id'   => $this->productId,
            'quantity'     => $this->quantity,
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
