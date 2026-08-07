<?php

/**
 * JSON Accepts Strategy.
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
use OpenSwoole\Core\Psr\Stream;
use Psr\Http\Message\StreamInterface;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;
use Throwable;

/**
 * Renders a response body as JSON.
 *
 * @see IAcceptsStrategy The contract, and why this knows no message.
 * @see FlatbufferAcceptsStrategy The other half of the choice.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class JsonAcceptsStrategy implements IAcceptsStrategy
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
            $built = $factory->createJson();

            if (!$built->isSuccess()) {
                // Not `$built` itself: it is a `Result` over the *rendered*
                // shape, and this method answers one over the stream.
                return Result::failure($built->getErrorId());
            }

            $encoded = json_encode($built->getValue(), JSON_THROW_ON_ERROR);

            return Result::success(Stream::streamFor($encoded));
        } catch (Throwable $error) {
            return Result::failure(Leaf::newError(new LeafContext(
                'Response could not be rendered as JSON: ' . $error->getMessage(),
                null,
                502,
            )));
        }
    }
}
