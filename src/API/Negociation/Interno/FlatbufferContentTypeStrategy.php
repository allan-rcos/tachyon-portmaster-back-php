<?php

/**
 * FlatBuffer Content Type Strategy.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation\Interno;

use API\Negociation\IContentTypeStrategy;
use API\Negociation\IRequestAbstractFactory;
use Google\FlatBuffers\ByteBuffer;
use Psr\Http\Message\StreamInterface;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;
use Throwable;

/**
 * Decodes a request body sent as a FlatBuffer.
 *
 * @see IContentTypeStrategy The contract, and why this knows no message.
 * @see JsonContentTypeStrategy The other half of the choice.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class FlatbufferContentTypeStrategy implements IContentTypeStrategy
{
    /**
     * {@inheritDoc}
     *
     * Both failures are the same kind: this method was asked for a message and
     * the request does not contain one. An empty body carries no table to read,
     * and bytes that do not parse as one are a client that announced this
     * format and did not deliver it. Neither reaches the factory — there is
     * nothing for it to build from.
     *
     * @template T of object
     *
     * @param  StreamInterface  $body  The request body.
     * @param  IRequestAbstractFactory<T>  $factory  Builds the message once the
     *                                               body has been decoded.
     * @return Result<T> The hydrated message, or the failure.
     *
     * @copyright 2026 Tachyon
     */
    public function execute(StreamInterface $body, IRequestAbstractFactory $factory): Result
    {
        $raw = (string) $body;

        if ($raw === '') {
            return Result::failure(Leaf::newError(new LeafContext(
                'Request body is empty; this endpoint reads a message from it.',
                null,
                404,
            )));
        }

        try {
            // `wrap()` leaves the read position unset, and every accessor below
            // it feeds that null straight into `substr()` — harmless, but PHP
            // deprecates the coercion loudly. Stating the position costs a line.
            $buffer = ByteBuffer::wrap($raw);
            $buffer->setPosition(0);

            return $factory->fromFlatbuffer($buffer);
        } catch (Throwable $error) {
            return Result::failure(Leaf::newError(new LeafContext(
                'Request body is not a readable FlatBuffer: ' . $error->getMessage(),
                null,
                404,
            )));
        }
    }
}
