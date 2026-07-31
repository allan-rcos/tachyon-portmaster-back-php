<?php

/**
 * Container Summary Response Proxy.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

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
 * JSON/binary-aware proxy around the generated {@see ContainerSummaryResponse} table.
 *
 * @see \API\Fbs\Server\ProjectInfoProxy The proxy shape this follows.
 * @see \API\Fbs\Contracts\IFbsProxy The contract, and why none of this validates.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final class ContainerSummaryResponseProxy extends ContainerSummaryResponse implements IFbsProxy
{
    use CoercesJson;

    /**
     * @param  list<CargoManifestItemProxy>  $manifest
     * @param  list<TelemetryLogItemProxy>  $recentLogs
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ?ContainerResponseProxy $container = null,
        public array $manifest = [],
        public array $recentLogs = [],
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
        $manifestOffsets = array_map(fn(CargoManifestItemProxy $item) => $item->buildInto($builder), $this->manifest);
        $logOffsets      = array_map(fn(TelemetryLogItemProxy $item) => $item->buildInto($builder), $this->recentLogs);
        $manifest        = ContainerSummaryResponse::createManifestVector($builder, $manifestOffsets);
        $recentLogs      = ContainerSummaryResponse::createRecentLogsVector($builder, $logOffsets);
        $container       = $this->container?->buildInto($builder) ?? 0;

        return ContainerSummaryResponse::createContainerSummaryResponse($builder, $container, $manifest, $recentLogs);
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
     * @param  ContainerSummaryResponse  $table  The generated table to read.
     * @return static The hydrated proxy.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function fromTable(ContainerSummaryResponse $table): static
    {
        $manifest = [];
        for ($i = 0, $n = $table->getManifestLength(); $i < $n; $i++) {
            $manifest[] = CargoManifestItemProxy::fromTable($table->getManifest($i));
        }

        $recentLogs = [];
        for ($i = 0, $n = $table->getRecentLogsLength(); $i < $n; $i++) {
            $recentLogs[] = TelemetryLogItemProxy::fromTable($table->getRecentLogs($i));
        }

        $container = $table->getContainer();

        return new static(
            container: $container instanceof ContainerResponse ? ContainerResponseProxy::fromTable($container) : null,
            manifest: $manifest,
            recentLogs: $recentLogs,
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
        return static::fromTable(ContainerSummaryResponse::getRootAsContainerSummaryResponse(ByteBuffer::wrap($binary)));
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
            container: ($obj = self::jsonObject($data, 'container')) !== null
                ? ContainerResponseProxy::jsonUnserialize($obj)
                : null,
            manifest: array_map(static fn (array $row): CargoManifestItemProxy => CargoManifestItemProxy::jsonUnserialize($row), self::jsonRows($data, 'manifest')),
            recentLogs: array_map(static fn (array $row): TelemetryLogItemProxy => TelemetryLogItemProxy::jsonUnserialize($row), self::jsonRows($data, 'recent_logs')),
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
            'container'   => $this->container?->jsonSerialize(),
            'manifest'    => array_map(fn(CargoManifestItemProxy $item) => $item->jsonSerialize(), $this->manifest),
            'recent_logs' => array_map(fn(TelemetryLogItemProxy $item) => $item->jsonSerialize(), $this->recentLogs),
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
