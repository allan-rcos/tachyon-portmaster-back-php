<?php

declare(strict_types=1);

namespace API\Fbs\Admin;

use API\Fbs\Account\RoleResponse;
use API\Fbs\Account\RoleResponseProxy;
use API\Fbs\Contracts\CoercesJson;
use API\Fbs\Contracts\IFbsProxy;
use API\Http\ContentKind;
use API\Http\RequestAttributes;
use Google\FlatBuffers\ByteBuffer;
use Google\FlatBuffers\FlatbufferBuilder;
use OpenSwoole\Core\Psr\Stream;
use Psr\Http\Message\StreamInterface;

/**
 * JSON/binary-aware proxy around the generated {@see RoleListResponse} table.
 */
final class RoleListResponseProxy extends RoleListResponse implements IFbsProxy
{
    use CoercesJson;

    /**
     * @param  list<RoleResponseProxy>  $data
     */
    public function __construct(
        public array $data = [],
        public ?string $nextCursor = null,
        public int $total = 0,
    ) {
    }

    public function buildInto(FlatbufferBuilder $builder): int
    {
        $itemOffsets = array_map(fn(RoleResponseProxy $item) => $item->buildInto($builder), $this->data);
        $data        = RoleListResponse::createDataVector($builder, $itemOffsets);
        $nextCursor  = $this->nextCursor !== null ? $builder->createString($this->nextCursor) : 0;

        return RoleListResponse::createRoleListResponse($builder, $data, $nextCursor, $this->total);
    }

    public function toBinary(): string
    {
        $builder = new FlatbufferBuilder(0);
        $builder->finish($this->buildInto($builder));

        return $builder->sizedByteArray();
    }

    public static function fromTable(RoleListResponse $table): static
    {
        $data = [];
        for ($i = 0, $n = $table->getDataLength(); $i < $n; $i++) {
            $data[] = RoleResponseProxy::fromTable($table->getData($i));
        }

        return new static(
            data: $data,
            nextCursor: $table->getNextCursor(),
            total: $table->getTotal(),
        );
    }

    public static function fromBinary(string $binary): static
    {
        // Parse into a proxy instance so the overridden getData() below — which
        // fixes flatc's mis-namespaced nested getter — is used while reading.
        $buffer = ByteBuffer::wrap($binary);
        $proxy  = new static();
        $proxy->init($buffer->getInt($buffer->getPosition()) + $buffer->getPosition(), $buffer);

        return static::fromTable($proxy);
    }

    /**
     * Overrides the generated {@see RoleListResponse::getData()}, which
     * instantiates an unqualified `RoleResponse` and therefore resolves to the
     * wrong (Admin) namespace on case-sensitive autoloaders.
     */
    /**
     * @param  int  $j
     */
    public function getData($j): ?RoleResponse
    {
        $offset = $this->__offset(4);
        if ($offset === 0) {
            return null;
        }

        /** @var int $vector */
        $vector = $this->__vector($offset);
        /** @var int $position */
        $position = $this->__indirect($vector + $j * 4);

        return (new RoleResponse())->init($position, $this->bb);
    }

    public static function jsonUnserialize(array $data): static
    {
        return new static(
            data: array_map(static fn (array $row): RoleResponseProxy => RoleResponseProxy::jsonUnserialize($row), self::jsonRows($data, 'data')),
            nextCursor: self::jsonNullableString($data, 'next_cursor'),
            total: self::jsonInt($data, 'total'),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'data'        => array_map(fn(RoleResponseProxy $item) => $item->jsonSerialize(), $this->data),
            'next_cursor' => $this->nextCursor,
            'total'       => $this->total,
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
