<?php

declare(strict_types=1);

namespace API\Fbs\Admin;

use API\Fbs\Contracts\CoercesJson;
use API\Fbs\Contracts\IFbsProxy;
use API\Http\ContentKind;
use API\Http\RequestAttributes;
use Google\FlatBuffers\ByteBuffer;
use Google\FlatBuffers\FlatbufferBuilder;
use OpenSwoole\Core\Psr\Stream;
use Psr\Http\Message\StreamInterface;

/**
 * JSON/binary-aware proxy around the generated {@see UserListResponse} table.
 */
final class UserListResponseProxy extends UserListResponse implements IFbsProxy
{
    use CoercesJson;

    /**
     * @param  list<UserAdminResponseProxy>  $data
     */
    public function __construct(
        public array $data = [],
    ) {
    }

    public function buildInto(FlatbufferBuilder $builder): int
    {
        $itemOffsets = array_map(
            static fn (UserAdminResponseProxy $item): int => $item->buildInto($builder),
            $this->data,
        );
        $data = UserListResponse::createDataVector($builder, $itemOffsets);

        return UserListResponse::createUserListResponse($builder, $data);
    }

    public function toBinary(): string
    {
        $builder = new FlatbufferBuilder(0);
        $builder->finish($this->buildInto($builder));

        return $builder->sizedByteArray();
    }

    public static function fromTable(UserListResponse $table): static
    {
        $data = [];
        for ($i = 0, $n = $table->getDataLength(); $i < $n; $i++) {
            $item = $table->getData($i);
            if ($item instanceof UserAdminResponse) {
                $data[] = UserAdminResponseProxy::fromTable($item);
            }
        }

        return new static(data: $data);
    }

    public static function fromBinary(string $binary): static
    {
        return static::fromTable(UserListResponse::getRootAsUserListResponse(ByteBuffer::wrap($binary)));
    }

    public static function jsonUnserialize(array $data): static
    {
        $items = [];
        foreach (self::jsonRows($data, 'data') as $item) {
            $items[] = UserAdminResponseProxy::jsonUnserialize($item);
        }

        return new static(data: $items);
    }

    public function jsonSerialize(): array
    {
        return ['data' => $this->data];
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
