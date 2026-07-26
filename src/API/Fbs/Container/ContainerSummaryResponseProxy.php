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
 * JSON/binary-aware proxy around the generated {@see ContainerSummaryResponse} table.
 */
final class ContainerSummaryResponseProxy extends ContainerSummaryResponse implements IFbsProxy
{
    use CoercesJson;

    /**
     * @param  list<CargoManifestItemProxy>  $manifest
     * @param  list<TelemetryLogItemProxy>  $recentLogs
     */
    public function __construct(
        public ?ContainerResponseProxy $container = null,
        public array $manifest = [],
        public array $recentLogs = [],
    ) {
    }

    public function buildInto(FlatbufferBuilder $builder): int
    {
        $manifestOffsets = array_map(fn(CargoManifestItemProxy $item) => $item->buildInto($builder), $this->manifest);
        $logOffsets      = array_map(fn(TelemetryLogItemProxy $item) => $item->buildInto($builder), $this->recentLogs);
        $manifest        = ContainerSummaryResponse::createManifestVector($builder, $manifestOffsets);
        $recentLogs      = ContainerSummaryResponse::createRecentLogsVector($builder, $logOffsets);
        $container       = $this->container?->buildInto($builder) ?? 0;

        return ContainerSummaryResponse::createContainerSummaryResponse($builder, $container, $manifest, $recentLogs);
    }

    public function toBinary(): string
    {
        $builder = new FlatbufferBuilder(0);
        $builder->finish($this->buildInto($builder));

        return $builder->sizedByteArray();
    }

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

    public static function fromBinary(string $binary): static
    {
        return static::fromTable(ContainerSummaryResponse::getRootAsContainerSummaryResponse(ByteBuffer::wrap($binary)));
    }

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

    public function jsonSerialize(): array
    {
        return [
            'container'   => $this->container?->jsonSerialize(),
            'manifest'    => array_map(fn(CargoManifestItemProxy $item) => $item->jsonSerialize(), $this->manifest),
            'recent_logs' => array_map(fn(TelemetryLogItemProxy $item) => $item->jsonSerialize(), $this->recentLogs),
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
