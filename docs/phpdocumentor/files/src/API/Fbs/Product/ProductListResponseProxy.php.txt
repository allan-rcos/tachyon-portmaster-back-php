<?php

/**
 * Product List Response Proxy.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Fbs\Product;

use API\Fbs\Contracts\CoercesJson;
use API\Fbs\Contracts\IFbsProxy;
use API\Http\ContentKind;
use API\Http\RequestAttributes;
use Google\FlatBuffers\ByteBuffer;
use Google\FlatBuffers\FlatbufferBuilder;
use OpenSwoole\Core\Psr\Stream;
use Psr\Http\Message\StreamInterface;

/**
 * JSON/binary-aware proxy around the generated {@see ProductListResponse} table.
 *
 * @see \API\Fbs\Server\ProjectInfoProxy The proxy shape this follows.
 * @see \API\Fbs\Contracts\IFbsProxy The contract, and why none of this validates.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final class ProductListResponseProxy extends ProductListResponse implements IFbsProxy
{
    use CoercesJson;

    /**
     * @param  list<ProductResponseProxy>  $data
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public array $data = [],
        public ?string $nextCursor = null,
        public int $total = 0,
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
        $itemOffsets = array_map(fn(ProductResponseProxy $item) => $item->buildInto($builder), $this->data);
        $data        = ProductListResponse::createDataVector($builder, $itemOffsets);
        $nextCursor  = $this->nextCursor !== null ? $builder->createString($this->nextCursor) : 0;

        return ProductListResponse::createProductListResponse($builder, $data, $nextCursor, $this->total);
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
     * @param  ProductListResponse  $table  The generated table to read.
     * @return static The hydrated proxy.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function fromTable(ProductListResponse $table): static
    {
        $data = [];
        for ($i = 0, $n = $table->getDataLength(); $i < $n; $i++) {
            $data[] = ProductResponseProxy::fromTable($table->getData($i));
        }

        return new static(
            data: $data,
            nextCursor: $table->getNextCursor(),
            total: $table->getTotal(),
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
        return static::fromTable(ProductListResponse::getRootAsProductListResponse(ByteBuffer::wrap($binary)));
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
            data: array_map(static fn (array $row): ProductResponseProxy => ProductResponseProxy::jsonUnserialize($row), self::jsonRows($data, 'data')),
            nextCursor: self::jsonNullableString($data, 'next_cursor'),
            total: self::jsonInt($data, 'total'),
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
            'data'        => array_map(fn(ProductResponseProxy $item) => $item->jsonSerialize(), $this->data),
            'next_cursor' => $this->nextCursor,
            'total'       => $this->total,
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
