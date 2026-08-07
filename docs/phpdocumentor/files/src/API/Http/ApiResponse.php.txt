<?php

/**
 * API Response.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Http;

use API\Negociation\IAcceptsStrategy;
use API\Negociation\IResponseAbstractFactory;
use OpenSwoole\Core\Psr\Response;
use Psr\Http\Message\ResponseInterface;
use Shared\Exceptions\Result;

/**
 * Builds successful API responses out of a message factory and the strategy
 * negotiated for the request.
 *
 * Controllers stay oblivious to json-vs-binary: they wrap their DTO in its
 * {@see IResponseAbstractFactory} and hand it here with the
 * {@see IAcceptsStrategy} they were injected with. The strategy renders the
 * body; the negotiation middleware names it on the way out, so neither this
 * class nor its caller ever has to know which format was chosen.
 *
 * There is deliberately **no** "plain JSON" escape hatch. Every response body
 * must be a FlatBuffers table declared in `swagger/flatbuffers/schemas` and
 * published in `swagger.json` — an endpoint answering with an ad-hoc structure
 * is an endpoint no client can discover.
 *
 * @see ProblemResponse The failure side.
 * @see IResponseAbstractFactory What every body must be wrapped in.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final class ApiResponse
{
    /**
     * A response carrying the factory's message as its (negotiated) body.
     *
     * Answers a {@see Result} because rendering can fail, and this is not the
     * place that decides what such a failure means: the controller receives it
     * and answers a problem document — a 502, since the message was built and
     * it is the *server* that could not put it on the wire.
     *
     * No `Content-Type` is set here. The bytes are one decision and their name
     * is another, and the second belongs to
     * {@see \API\Http\Middleware\ContentNegotiationMiddleware}, which read the
     * `Accept` header in the first place and labels every answer on the way out.
     *
     * @param  IAcceptsStrategy  $accepts  Renders the body; in practice the
     *                                     request's {@see \API\Negociation\Interno\AcceptsStrategyContext}.
     * @param  IResponseAbstractFactory  $factory  Wraps the message to serialize.
     * @param  int  $status  HTTP status; 201 for a create, 200 otherwise.
     * @return Result<ResponseInterface> The outgoing HTTP response.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function body(
        IAcceptsStrategy $accepts,
        IResponseAbstractFactory $factory,
        int $status = 200,
    ): Result {
        $body = $accepts->toStream($factory);

        if (!$body->isSuccess()) {
            return Result::failure($body->getErrorId());
        }

        return Result::success(new Response((string) $body->getValue(), $status));
    }

    /**
     * An empty `204 No Content` response.
     *
     * No `Content-Type`, since there is no content to describe — this is what a
     * delete answers with, and the one answer that needs no negotiation.
     *
     * @return ResponseInterface The outgoing HTTP response.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function noContent(): ResponseInterface
    {
        return new Response('', 204);
    }
}
