<?php

declare(strict_types=1);

namespace API\Fbs\Common;

use API\Fbs\Contracts\CoercesJson;
use API\Fbs\Contracts\IFbsProxy;
use API\Http\ContentKind;
use API\Http\RequestAttributes;
use Google\FlatBuffers\ByteBuffer;
use Google\FlatBuffers\FlatbufferBuilder;
use OpenSwoole\Core\Psr\Stream;
use Psr\Http\Message\StreamInterface;

/**
 * JSON/binary-aware proxy around the generated {@see ProblemDetails} table.
 */
final class ProblemDetailsProxy extends ProblemDetails implements IFbsProxy
{
    use CoercesJson;

    public function __construct(
        public ?string $type = null,
        public ?string $title = null,
        public int $status = 0,
        public ?string $detail = null,
        public ?string $instance = null,
    ) {
    }

    public function buildInto(FlatbufferBuilder $builder): int
    {
        $type     = $this->type !== null ? $builder->createString($this->type) : 0;
        $title    = $this->title !== null ? $builder->createString($this->title) : 0;
        $detail   = $this->detail !== null ? $builder->createString($this->detail) : 0;
        $instance = $this->instance !== null ? $builder->createString($this->instance) : 0;

        return ProblemDetails::createProblemDetails($builder, $type, $title, $this->status, $detail, $instance);
    }

    public function toBinary(): string
    {
        $builder = new FlatbufferBuilder(0);
        $builder->finish($this->buildInto($builder));

        return $builder->sizedByteArray();
    }

    public static function fromTable(ProblemDetails $table): static
    {
        return new static(
            type: $table->getType(),
            title: $table->getTitle(),
            status: $table->getStatus(),
            detail: $table->getDetail(),
            instance: $table->getInstance(),
        );
    }

    public static function fromBinary(string $binary): static
    {
        return static::fromTable(ProblemDetails::getRootAsProblemDetails(ByteBuffer::wrap($binary)));
    }

    public static function jsonUnserialize(array $data): static
    {
        return new static(
            type: self::jsonNullableString($data, 'type'),
            title: self::jsonNullableString($data, 'title'),
            status: self::jsonInt($data, 'status'),
            detail: self::jsonNullableString($data, 'detail'),
            instance: self::jsonNullableString($data, 'instance'),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'type'     => $this->type,
            'title'    => $this->title,
            'status'   => $this->status,
            'detail'   => $this->detail,
            'instance' => $this->instance,
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
