<?php

/**
 * Manifest Response Proxy.
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
 *
 * @see \API\Fbs\Server\ProjectInfoProxy The proxy shape this follows.
 * @see \API\Fbs\Contracts\IFbsProxy The contract, and why none of this validates.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final class ManifestResponseProxy extends ManifestResponse implements IFbsProxy
{
    use CoercesJson;

    /**
     * Every field defaults, so `new static()` is a valid empty message.
     *
     * @param  ?string  $message  What happened, in prose.
     * @param  ?ContainerResponseProxy  $container  The container as it now stands.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ?string $message = null,
        public ?ContainerResponseProxy $container = null,
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
        $container = $this->container?->buildInto($builder) ?? 0;
        $message   = $this->message !== null ? $builder->createString($this->message) : 0;

        return ManifestResponse::createManifestResponse($builder, $message, $container);
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
     * @param  ManifestResponse  $table  The generated table to read.
     * @return static The hydrated proxy.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function fromTable(ManifestResponse $table): static
    {
        $container = $table->getContainer();

        return new static(
            message: $table->getMessage(),
            container: $container instanceof ContainerResponse ? ContainerResponseProxy::fromTable($container) : null,
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
     *
     * @copyright 2026 Tachyon
     *
     * @api
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
            message: self::jsonNullableString($data, 'message'),
            container: ($obj = self::jsonObject($data, 'container')) !== null
                ? ContainerResponseProxy::jsonUnserialize($obj)
                : null,
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
            'message'   => $this->message,
            'container' => $this->container?->jsonSerialize(),
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
