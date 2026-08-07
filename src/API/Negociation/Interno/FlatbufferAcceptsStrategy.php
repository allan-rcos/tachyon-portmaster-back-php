<?php

/**
 * FlatBuffer Accepts Strategy.
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

use API\Negociation\IAcceptsStrategy;
use API\Negociation\IResponseAbstractFactory;
use Google\FlatBuffers\FlatbufferBuilder;
use OpenSwoole\Core\Psr\Stream;
use Psr\Http\Message\StreamInterface;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;
use Throwable;

/**
 * Renders a response body as a FlatBuffer.
 *
 * @see IAcceptsStrategy The contract, and why this knows no message.
 * @see JsonAcceptsStrategy The other half of the choice.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class FlatbufferAcceptsStrategy implements IAcceptsStrategy
{
    /**
     * {@inheritDoc}
     *
     * @param  IResponseAbstractFactory  $factory  Wraps the message to render.
     * @return Result<StreamInterface> The response body.
     *
     * @copyright 2026 Tachyon
     */
    public function toStream(IResponseAbstractFactory $factory): Result
    {
        try {
            // The builder belongs to the strategy, from here to the bytes: a
            // factory maps a message onto a table and never decides when the
            // buffer is closed. It is also what lets a whole tree of messages
            // share one buffer, which is what FlatBuffers requires of nesting.
            $builder = new FlatbufferBuilder(0);

            $built = $factory->createFlatbuffer($builder);

            if (!$built->isSuccess()) {
                // Not `$built` itself: it is a `Result` over the root table's
                // offset, and this method answers one over the stream.
                return Result::failure($built->getErrorId());
            }

            $builder->finish($built->getValue());

            return Result::success(Stream::streamFor($builder->sizedByteArray()));
        } catch (Throwable $error) {
            return Result::failure(Leaf::newError(new LeafContext(
                'Response could not be rendered as a FlatBuffer: ' . $error->getMessage(),
                null,
                502,
            )));
        }
    }
}
