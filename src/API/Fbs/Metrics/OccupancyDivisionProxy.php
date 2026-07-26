<?php

declare(strict_types=1);

namespace API\Fbs\Metrics;

use API\Fbs\Contracts\CoercesJson;
use API\Fbs\Contracts\IFbsProxy;
use API\Http\ContentKind;
use API\Http\RequestAttributes;
use Google\FlatBuffers\ByteBuffer;
use Google\FlatBuffers\FlatbufferBuilder;
use OpenSwoole\Core\Psr\Stream;
use Psr\Http\Message\StreamInterface;

/**
 * JSON/binary-aware proxy around the generated {@see OccupancyDivision} table.
 */
final class OccupancyDivisionProxy extends OccupancyDivision implements IFbsProxy
{
    use CoercesJson;

    public function __construct(
        public int $empty = 0,
        public int $loading = 0,
        public int $sealed = 0,
        public int $inTransit = 0,
    ) {
    }

    public function buildInto(FlatbufferBuilder $builder): int
    {
        return OccupancyDivision::createOccupancyDivision($builder, $this->empty, $this->loading, $this->sealed, $this->inTransit);
    }

    public function toBinary(): string
    {
        $builder = new FlatbufferBuilder(0);
        $builder->finish($this->buildInto($builder));

        return $builder->sizedByteArray();
    }

    public static function fromTable(OccupancyDivision $table): static
    {
        return new static(
            empty: $table->getEmpty(),
            loading: $table->getLoading(),
            sealed: $table->getSealed(),
            inTransit: $table->getInTransit(),
        );
    }

    public static function fromBinary(string $binary): static
    {
        return static::fromTable(OccupancyDivision::getRootAsOccupancyDivision(ByteBuffer::wrap($binary)));
    }

    public static function jsonUnserialize(array $data): static
    {
        return new static(
            empty: self::jsonInt($data, 'empty'),
            loading: self::jsonInt($data, 'loading'),
            sealed: self::jsonInt($data, 'sealed'),
            inTransit: self::jsonInt($data, 'in_transit'),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'empty'      => $this->empty,
            'loading'    => $this->loading,
            'sealed'     => $this->sealed,
            'in_transit' => $this->inTransit,
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
