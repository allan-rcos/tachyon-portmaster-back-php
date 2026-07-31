<?php

/**
 * Problem Details Proxy.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

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
 *
 * @see \API\Fbs\Server\ProjectInfoProxy The proxy shape this follows.
 * @see \API\Fbs\Contracts\IFbsProxy The contract, and why none of this validates.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final class ProblemDetailsProxy extends ProblemDetails implements IFbsProxy
{
    use CoercesJson;

    /**
     * Every field defaults, so `new static()` is a valid empty message.
     *
     * @param  ?string  $type  URI identifying the problem kind; `about:blank` when the status says it all.
     * @param  ?string  $title  Short, stable summary of the problem kind.
     * @param  int  $status  HTTP status code.
     * @param  ?string  $detail  What went wrong on this occasion.
     * @param  ?string  $instance  URI of the specific occurrence.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ?string $type = null,
        public ?string $title = null,
        public int $status = 0,
        public ?string $detail = null,
        public ?string $instance = null,
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
        $type     = $this->type !== null ? $builder->createString($this->type) : 0;
        $title    = $this->title !== null ? $builder->createString($this->title) : 0;
        $detail   = $this->detail !== null ? $builder->createString($this->detail) : 0;
        $instance = $this->instance !== null ? $builder->createString($this->instance) : 0;

        return ProblemDetails::createProblemDetails($builder, $type, $title, $this->status, $detail, $instance);
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
     * @param  ProblemDetails  $table  The generated table to read.
     * @return static The hydrated proxy.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
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
        return static::fromTable(ProblemDetails::getRootAsProblemDetails(ByteBuffer::wrap($binary)));
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
            type: self::jsonNullableString($data, 'type'),
            title: self::jsonNullableString($data, 'title'),
            status: self::jsonInt($data, 'status'),
            detail: self::jsonNullableString($data, 'detail'),
            instance: self::jsonNullableString($data, 'instance'),
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
            'type'     => $this->type,
            'title'    => $this->title,
            'status'   => $this->status,
            'detail'   => $this->detail,
            'instance' => $this->instance,
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
