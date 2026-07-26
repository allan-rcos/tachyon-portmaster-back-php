<?php

declare(strict_types=1);

namespace API\Fbs\Product;

use API\Fbs\Contracts\CoercesJson;
use API\Fbs\Contracts\IFbsProxy;
use API\Http\ContentKind;
use API\Http\RequestAttributes;
use Domain\Enums\RiskClass;
use Google\FlatBuffers\ByteBuffer;
use Google\FlatBuffers\FlatbufferBuilder;
use OpenSwoole\Core\Psr\Stream;
use Psr\Http\Message\StreamInterface;

/**
 * JSON/binary-aware proxy around the generated {@see ProductCreateRequest} table.
 */
final class ProductCreateRequestProxy extends ProductCreateRequest implements IFbsProxy
{
    use CoercesJson;

    public function __construct(
        public ?string $name = null,
        public float $density = 0.0,
        public RiskClass $riskClass = RiskClass::Class1Explosives,
    ) {
    }

    public function buildInto(FlatbufferBuilder $builder): int
    {
        $name = $this->name !== null ? $builder->createString($this->name) : 0;

        return ProductCreateRequest::createProductCreateRequest($builder, $name, $this->density, $this->riskClass->toInt());
    }

    public function toBinary(): string
    {
        $builder = new FlatbufferBuilder(0);
        $builder->finish($this->buildInto($builder));

        return $builder->sizedByteArray();
    }

    public static function fromTable(ProductCreateRequest $table): static
    {
        return new static(
            name: $table->getName(),
            density: $table->getDensity(),
            riskClass: RiskClass::fromInt((int) $table->getRiskClass()),
        );
    }

    public static function fromBinary(string $binary): static
    {
        return static::fromTable(ProductCreateRequest::getRootAsProductCreateRequest(ByteBuffer::wrap($binary)));
    }

    public static function jsonUnserialize(array $data): static
    {
        return new static(
            name: self::jsonNullableString($data, 'name'),
            density: self::jsonFloat($data, 'density'),
            riskClass: RiskClass::tryFrom(self::jsonString($data, 'risk_class')) ?? RiskClass::Class1Explosives,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'name'       => $this->name,
            'density'    => $this->density,
            'risk_class' => $this->riskClass->value,
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
