<?php

/**
 * JSON Content Type Strategy.
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
use Psr\Http\Message\StreamInterface;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

/**
 * Decodes a request body sent as JSON.
 *
 * @see IContentTypeStrategy The contract, and why this knows no message.
 * @see FlatbufferContentTypeStrategy The other half of the choice.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class JsonContentTypeStrategy implements IContentTypeStrategy
{
    /**
     * {@inheritDoc}
     *
     * Both failures are the same kind: this method was asked for a message and
     * the request does not contain one. A body of nothing but whitespace has no
     * object to decode, and anything that does not decode to one — malformed, a
     * bare scalar, a list — is a client that sent something and called it JSON.
     * Neither reaches the factory.
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
        $raw = trim((string) $body);

        if ($raw === '') {
            return Result::failure(Leaf::newError(new LeafContext(
                'Request body is empty; this endpoint reads a message from it.',
                null,
                404,
            )));
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return Result::failure(Leaf::newError(new LeafContext(
                'Request body is not a readable JSON object.',
                null,
                404,
            )));
        }

        /** @var array<string, mixed> $decoded */
        return $factory->fromJson($decoded);
    }
}
