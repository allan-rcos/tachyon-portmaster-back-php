<?php

declare(strict_types=1);

namespace API\Fbs\Server;

use API\Fbs\Contracts\CoercesJson;
use API\Fbs\Contracts\IFbsProxy;
use API\Http\ContentKind;
use API\Http\RequestAttributes;
use Google\FlatBuffers\ByteBuffer;
use Google\FlatBuffers\FlatbufferBuilder;
use OpenSwoole\Core\Psr\Stream;
use Psr\Http\Message\StreamInterface;

/**
 * JSON/binary-aware proxy around the generated {@see ProjectInfo} table.
 *
 * Replaces the old `ProjectInfoDTO` + hand-rolled `json_encode` in the
 * controller: `GET /info` is now a declared, published contract like every other
 * endpoint, and it honours content negotiation for free.
 */
final class ProjectInfoProxy extends ProjectInfo implements IFbsProxy
{
    use CoercesJson;

    public function __construct(
        public ?string $name = null,
        public ?string $version = null,
        public ?string $environment = null,
        public ?string $runtime = null,
        public float $memoryUsageMb = 0.0,
    ) {
    }

    public function buildInto(FlatbufferBuilder $builder): int
    {
        $name = $this->name !== null ? $builder->createString($this->name) : 0;
        $version = $this->version !== null ? $builder->createString($this->version) : 0;
        $environment = $this->environment !== null ? $builder->createString($this->environment) : 0;
        $runtime = $this->runtime !== null ? $builder->createString($this->runtime) : 0;

        return ProjectInfo::createProjectInfo(
            $builder,
            $name,
            $version,
            $environment,
            $runtime,
            $this->memoryUsageMb,
        );
    }

    public function toBinary(): string
    {
        $builder = new FlatbufferBuilder(0);
        $builder->finish($this->buildInto($builder));

        return $builder->sizedByteArray();
    }

    public static function fromTable(ProjectInfo $table): static
    {
        return new static(
            name: $table->getName(),
            version: $table->getVersion(),
            environment: $table->getEnvironment(),
            runtime: $table->getRuntime(),
            memoryUsageMb: (float) $table->getMemoryUsageMb(),
        );
    }

    public static function fromBinary(string $binary): static
    {
        return static::fromTable(ProjectInfo::getRootAsProjectInfo(ByteBuffer::wrap($binary)));
    }

    public static function jsonUnserialize(array $data): static
    {
        return new static(
            name: self::jsonNullableString($data, 'name'),
            version: self::jsonNullableString($data, 'version'),
            environment: self::jsonNullableString($data, 'environment'),
            runtime: self::jsonNullableString($data, 'runtime'),
            memoryUsageMb: self::jsonFloat($data, 'memory_usage_mb'),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'environment' => $this->environment,
            'runtime' => $this->runtime,
            'memory_usage_mb' => $this->memoryUsageMb,
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
