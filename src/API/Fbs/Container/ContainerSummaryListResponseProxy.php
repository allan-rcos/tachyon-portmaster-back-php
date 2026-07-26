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
 * JSON/binary-aware proxy around the generated {@see ContainerSummaryListResponse} table.
 */
final class ContainerSummaryListResponseProxy extends ContainerSummaryListResponse implements IFbsProxy
{
    use CoercesJson;

    /**
     * @param  list<ContainerSummaryResponseProxy>  $data
     */
    public function __construct(
        public array $data = [],
        public ?string $nextCursor = null,
        public int $total = 0,
    ) {
    }

    public function buildInto(FlatbufferBuilder $builder): int
    {
        $itemOffsets = array_map(fn(ContainerSummaryResponseProxy $item) => $item->buildInto($builder), $this->data);
        $data        = ContainerSummaryListResponse::createDataVector($builder, $itemOffsets);
        $nextCursor  = $this->nextCursor !== null ? $builder->createString($this->nextCursor) : 0;

        return ContainerSummaryListResponse::createContainerSummaryListResponse($builder, $data, $nextCursor, $this->total);
    }

    public function toBinary(): string
    {
        $builder = new FlatbufferBuilder(0);
        $builder->finish($this->buildInto($builder));

        return $builder->sizedByteArray();
    }

    public static function fromTable(ContainerSummaryListResponse $table): static
    {
        $data = [];
        for ($i = 0, $n = $table->getDataLength(); $i < $n; $i++) {
            $data[] = ContainerSummaryResponseProxy::fromTable($table->getData($i));
        }

        return new static(
            data: $data,
            nextCursor: $table->getNextCursor(),
            total: $table->getTotal(),
        );
    }

    public static function fromBinary(string $binary): static
    {
        return static::fromTable(ContainerSummaryListResponse::getRootAsContainerSummaryListResponse(ByteBuffer::wrap($binary)));
    }

    public static function jsonUnserialize(array $data): static
    {
        return new static(
            data: array_map(static fn (array $row): ContainerSummaryResponseProxy => ContainerSummaryResponseProxy::jsonUnserialize($row), self::jsonRows($data, 'data')),
            nextCursor: self::jsonNullableString($data, 'next_cursor'),
            total: self::jsonInt($data, 'total'),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'data'        => array_map(fn(ContainerSummaryResponseProxy $item) => $item->jsonSerialize(), $this->data),
            'next_cursor' => $this->nextCursor,
            'total'       => $this->total,
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
