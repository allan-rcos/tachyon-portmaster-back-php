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
 * JSON/binary-aware proxy around the generated {@see TelemetryLogItem} table.
 */
final class TelemetryLogItemProxy extends TelemetryLogItem implements IFbsProxy
{
    use CoercesJson;

    public function __construct(
        public ?string $id = null,
        /** Telemetry event slug; the registry is the runtime catalogue. */
        public ?string $event = null,
        public ?string $description = null,
        public ?string $timestamp = null,
    ) {
    }

    public function buildInto(FlatbufferBuilder $builder): int
    {
        $id = $this->id !== null ? $builder->createString($this->id) : 0;
        $event       = $this->event !== null ? $builder->createString($this->event) : 0;
        $description = $this->description !== null ? $builder->createString($this->description) : 0;
        $timestamp   = $this->timestamp !== null ? $builder->createString($this->timestamp) : 0;

        return TelemetryLogItem::createTelemetryLogItem($builder, $id, $event, $description, $timestamp);
    }

    public function toBinary(): string
    {
        $builder = new FlatbufferBuilder(0);
        $builder->finish($this->buildInto($builder));

        return $builder->sizedByteArray();
    }

    public static function fromTable(TelemetryLogItem $table): static
    {
        return new static(
            id: $table->getId(),
            event: $table->getEvent(),
            description: $table->getDescription(),
            timestamp: $table->getTimestamp(),
        );
    }

    public static function fromBinary(string $binary): static
    {
        return static::fromTable(TelemetryLogItem::getRootAsTelemetryLogItem(ByteBuffer::wrap($binary)));
    }

    public static function jsonUnserialize(array $data): static
    {
        return new static(
            id: self::jsonNullableString($data, 'id'),
            event: self::jsonNullableString($data, 'event'),
            description: self::jsonNullableString($data, 'description'),
            timestamp: self::jsonNullableString($data, 'timestamp'),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id'          => $this->id,
            'event'       => $this->event,
            'description' => $this->description,
            'timestamp'   => $this->timestamp,
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
