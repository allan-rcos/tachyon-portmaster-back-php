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
 * JSON/binary-aware proxy around the generated {@see MetricsResponse} table.
 */
final class MetricsResponseProxy extends MetricsResponse implements IFbsProxy
{
    use CoercesJson;

    public function __construct(
        public int $activeContainers = 0,
        public int $totalContainers = 0,
        public float $yardLoad = 0.0,
        public int $registeredProducts = 0,
        public ?OccupancyDivisionProxy $occupancyDivision = null,
    ) {
    }

    public function buildInto(FlatbufferBuilder $builder): int
    {
        $occupancyDivision = $this->occupancyDivision?->buildInto($builder) ?? 0;

        return MetricsResponse::createMetricsResponse(
            $builder,
            $this->activeContainers,
            $this->totalContainers,
            $this->yardLoad,
            $this->registeredProducts,
            $occupancyDivision,
        );
    }

    public function toBinary(): string
    {
        $builder = new FlatbufferBuilder(0);
        $builder->finish($this->buildInto($builder));

        return $builder->sizedByteArray();
    }

    public static function fromTable(MetricsResponse $table): static
    {
        $occupancyDivision = $table->getOccupancyDivision();

        return new static(
            activeContainers: $table->getActiveContainers(),
            totalContainers: $table->getTotalContainers(),
            yardLoad: $table->getYardLoad(),
            registeredProducts: $table->getRegisteredProducts(),
            occupancyDivision: $occupancyDivision instanceof OccupancyDivision
                ? OccupancyDivisionProxy::fromTable($occupancyDivision)
                : null,
        );
    }

    public static function fromBinary(string $binary): static
    {
        return static::fromTable(MetricsResponse::getRootAsMetricsResponse(ByteBuffer::wrap($binary)));
    }

    public static function jsonUnserialize(array $data): static
    {
        return new static(
            activeContainers: self::jsonInt($data, 'active_containers'),
            totalContainers: self::jsonInt($data, 'total_containers'),
            yardLoad: self::jsonFloat($data, 'yard_load'),
            registeredProducts: self::jsonInt($data, 'registered_products'),
            occupancyDivision: ($obj = self::jsonObject($data, 'occupancy_division')) !== null
                ? OccupancyDivisionProxy::jsonUnserialize($obj)
                : null,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'active_containers'   => $this->activeContainers,
            'total_containers'    => $this->totalContainers,
            'yard_load'           => $this->yardLoad,
            'registered_products' => $this->registeredProducts,
            'occupancy_division'  => $this->occupancyDivision?->jsonSerialize(),
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
