<?php

/**
 * Accepts Strategy Contract.
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
 * Serializes a response body in one wire format.
 *
 * The outbound mirror of {@see IContentTypeStrategy}, resolved from `Accept`
 * rather than `Content-Type` — the two are independent, and a caller may POST
 * JSON yet ask for binary back. Two implementations for the whole application,
 * neither of which knows any message.
 *
 * It renders bytes and nothing else. Naming them is
 * {@see \API\Http\ContentKind::mediaType()}'s job, applied once by the
 * negotiation middleware: a strategy that could be asked which media type it is
 * would be a strategy its caller can identify, and no caller is meant to know
 * which of the two it is holding.
 *
 * @see IContentTypeStrategy The inbound half.
 * @see IAcceptsStrategyManager The setter side, which only the middleware sees.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IAcceptsStrategy
{
    /**
     * Serializes the message the factory holds into a response body.
     *
     * A failure here is the server's, not the caller's: the message was already
     * built and this format could not express it. The caller turns that into a
     * 502 — see {@see \API\Http\ApiResponse::body()}.
     *
     * @param  IResponseAbstractFactory  $factory  Wraps the message to render.
     * @return Result<StreamInterface> The response body.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function toStream(IResponseAbstractFactory $factory): Result;
}
