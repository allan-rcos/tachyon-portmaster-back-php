<?php

declare(strict_types=1);

namespace API\Fbs\Manifest;

use API\Fbs\Container\ContainerResponse;
use API\Fbs\Container\ContainerResponseProxy;
use API\Fbs\Contracts\CoercesJson;
use API\Fbs\Contracts\IFbsProxy;
use API\Http\ContentKind;
use API\Http\RequestAttributes;
use Google\FlatBuffers\ByteBuffer;
use Google\FlatBuffers\FlatbufferBuilder;
use OpenSwoole\Core\Psr\Stream;
use Psr\Http\Message\StreamInterface;

/**
 * JSON/binary-aware proxy around the generated {@see ManifestResponse} table.
 */
final class ManifestResponseProxy extends ManifestResponse implements IFbsProxy
{
    use CoercesJson;

    public function __construct(
        public ?string $message = null,
        public ?ContainerResponseProxy $container = null,
    ) {
    }

    public function buildInto(FlatbufferBuilder $builder): int
    {
        $container = $this->container?->buildInto($builder) ?? 0;
        $message   = $this->message !== null ? $builder->createString($this->message) : 0;

        return ManifestResponse::createManifestResponse($builder, $message, $container);
    }

    public function toBinary(): string
    {
        $builder = new FlatbufferBuilder(0);
        $builder->finish($this->buildInto($builder));

        return $builder->sizedByteArray();
    }

    public static function fromTable(ManifestResponse $table): static
    {
        $container = $table->getContainer();

        return new static(
            message: $table->getMessage(),
            container: $container instanceof ContainerResponse ? ContainerResponseProxy::fromTable($container) : null,
        );
    }

    public static function fromBinary(string $binary): static
    {
        // Parse into a proxy instance (not the base table) so the overridden
        // getContainer() below — which fixes flatc's mis-namespaced nested
        // getter — is used while reading.
        $buffer = ByteBuffer::wrap($binary);
        $proxy  = new static();
        $proxy->init($buffer->getInt($buffer->getPosition()) + $buffer->getPosition(), $buffer);

        return static::fromTable($proxy);
    }

    /**
     * Overrides the generated {@see ManifestResponse::getContainer()}, which
     * instantiates an unqualified `ContainerResponse` and therefore resolves to
     * the wrong (Manifest) namespace on case-sensitive autoloaders.
     */
    public function getContainer(): ?ContainerResponse
    {
        $offset = $this->__offset(6);
        if ($offset === 0) {
            return null;
        }

        /** @var int $position */
        $position = $this->__indirect($offset + $this->bb_pos);

        return (new ContainerResponse())->init($position, $this->bb);
    }

    public static function jsonUnserialize(array $data): static
    {
        return new static(
            message: self::jsonNullableString($data, 'message'),
            container: ($obj = self::jsonObject($data, 'container')) !== null
                ? ContainerResponseProxy::jsonUnserialize($obj)
                : null,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'message'   => $this->message,
            'container' => $this->container?->jsonSerialize(),
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
