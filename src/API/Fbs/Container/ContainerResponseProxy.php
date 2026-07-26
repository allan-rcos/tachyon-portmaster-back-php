<?php

declare(strict_types=1);

namespace API\Fbs\Container;

use API\Fbs\Contracts\CoercesJson;
use API\Fbs\Contracts\IFbsProxy;
use API\Http\ContentKind;
use API\Http\RequestAttributes;
use Domain\Enums\ContainerStatus;
use Google\FlatBuffers\ByteBuffer;
use Google\FlatBuffers\FlatbufferBuilder;
use OpenSwoole\Core\Psr\Stream;
use Psr\Http\Message\StreamInterface;

/**
 * JSON/binary-aware proxy around the generated {@see ContainerResponse} table.
 */
final class ContainerResponseProxy extends ContainerResponse implements IFbsProxy
{
    use CoercesJson;

    public function __construct(
        public ?string $id = null,
        public ?string $code = null,
        public float $currentWeight = 0.0,
        public float $maxCapacity = 0.0,
        public ContainerStatus $status = ContainerStatus::Empty,
    ) {
    }

    public function buildInto(FlatbufferBuilder $builder): int
    {
        $id = $this->id !== null ? $builder->createString($this->id) : 0;
        $code = $this->code !== null ? $builder->createString($this->code) : 0;

        return ContainerResponse::createContainerResponse(
            $builder,
            $id,
            $code,
            $this->currentWeight,
            $this->maxCapacity,
            $this->status->toInt(),
        );
    }

    public function toBinary(): string
    {
        $builder = new FlatbufferBuilder(0);
        $builder->finish($this->buildInto($builder));

        return $builder->sizedByteArray();
    }

    public static function fromTable(ContainerResponse $table): static
    {
        return new static(
            id: $table->getId(),
            code: $table->getCode(),
            currentWeight: $table->getCurrentWeight(),
            maxCapacity: $table->getMaxCapacity(),
            status: ContainerStatus::fromInt((int) $table->getStatus()),
        );
    }

    public static function fromBinary(string $binary): static
    {
        return static::fromTable(ContainerResponse::getRootAsContainerResponse(ByteBuffer::wrap($binary)));
    }

    public static function jsonUnserialize(array $data): static
    {
        return new static(
            id: self::jsonNullableString($data, 'id'),
            code: self::jsonNullableString($data, 'code'),
            currentWeight: self::jsonFloat($data, 'current_weight'),
            maxCapacity: self::jsonFloat($data, 'max_capacity'),
            status: ContainerStatus::tryFrom(self::jsonString($data, 'status')) ?? ContainerStatus::Empty,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id'             => $this->id,
            'code'           => $this->code,
            'current_weight' => $this->currentWeight,
            'max_capacity'   => $this->maxCapacity,
            'status'         => $this->status->value,
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
