<?php

declare(strict_types=1);

namespace API\Fbs\Contracts;

use Google\FlatBuffers\FlatbufferBuilder;
use JsonSerializable;
use Psr\Http\Message\StreamInterface;

/**
 * Contract for the FlatBuffers message proxies.
 *
 * A proxy extends its generated flatc table unchanged and adds the ability to
 * be (de)serialized as either FlatBuffer binary or JSON. The wire format is not
 * decided by the proxy: the negotiation middleware reads the request headers
 * and records the request/response {@see \API\Http\ContentKind} in the coroutine
 * context, and {@see fromStream()} / {@see toStream()} honour it. This keeps the
 * controller oblivious to negotiation — it just asks the proxy for an object
 * from the request body and returns the proxy as a response body stream.
 */
interface IFbsProxy extends JsonSerializable
{
    /**
     * Appends this message to the builder and returns its table offset. All
     * nested offsets (strings, vectors, child tables) must be created first.
     */
    public function buildInto(FlatbufferBuilder $builder): int;

    /**
     * The FlatBuffer binary representation of this message.
     */
    public function toBinary(): string;

    /**
     * Hydrates a new instance from a FlatBuffer binary blob.
     */
    public static function fromBinary(string $binary): static;

    /**
     * Hydrates a new instance from a decoded JSON/associative structure.
     *
     * @param  array<string, mixed>  $data
     */
    public static function jsonUnserialize(array $data): static;

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array;

    /**
     * Builds the proxy from a request body, decoding it as the request
     * {@see \API\Http\ContentKind} recorded in the coroutine context.
     */
    public static function fromStream(StreamInterface $body): static;

    /**
     * Serializes the proxy to a response body stream, encoding it as the
     * response {@see \API\Http\ContentKind} recorded in the coroutine context.
     */
    public function toStream(): StreamInterface;
}
