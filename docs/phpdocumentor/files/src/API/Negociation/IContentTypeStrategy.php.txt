<?php

/**
 * Content Type Strategy Contract.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation;

use Psr\Http\Message\StreamInterface;
use Shared\Exceptions\Result;

/**
 * Deserializes a request body in one wire format.
 *
 * The *how* to {@see IRequestAbstractFactory}'s *what*: this knows a technique
 * — decode JSON, read a FlatBuffer — and nothing about any message. There are
 * exactly two implementations for the whole application, which is the point:
 * the choice between binary and JSON is made once, by
 * {@see \API\Http\Middleware\ContentNegotiationMiddleware}, from the
 * `Content-Type` header.
 *
 * Controllers depend on this interface and never on a concrete strategy. What
 * the provider hands them is
 * {@see \API\Negociation\Interno\ContentTypeStrategyContext}, which resolves the
 * request's strategy out of the coroutine context on every call — so a
 * controller built once per worker still decodes each request the way that
 * request asked for.
 *
 * @see IAcceptsStrategy The outbound half.
 * @see IContentTypeStrategyManager The setter side, which only the middleware sees.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IContentTypeStrategy
{
    /**
     * Reads the body and hands it to the factory in the shape this format
     * produces.
     *
     * Two states, and the controller decides what the second one means:
     *
     * - a **success**, which always carries the message;
     * - a **failure**, for a request this method was asked to read a message
     *   out of and could not — no body at all, or a body that does not parse in
     *   the format the client announced. Only an action that reads a body calls
     *   this at all, so "there is none" and "it is unreadable" are the same
     *   answer to the same question.
     *
     * A caller checks the failure and then uses the value. It never checks the
     * value, and nothing here ever hands back a message it invented.
     *
     * @template T of object
     *
     * @param  StreamInterface  $body  The request body.
     * @param  IRequestAbstractFactory<T>  $factory  Builds the message once the
     *                                               body has been decoded.
     * @return Result<T> The hydrated message, or the failure.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function execute(StreamInterface $body, IRequestAbstractFactory $factory): Result;
}
