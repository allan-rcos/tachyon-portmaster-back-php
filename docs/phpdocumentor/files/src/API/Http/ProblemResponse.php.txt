<?php

/**
 * Problem Response.
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

use API\Fbs\Common\ProblemDetailsProxy;
use OpenSwoole\Core\Psr\Response;
use Psr\Http\Message\ResponseInterface;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * Builds standardized error responses as RFC 7807 problem details.
 *
 * Errors are always emitted as `application/problem+json`, independent of
 * content negotiation, so an error never depends on (or fails because of) the
 * FlatBuffer machinery. {@see \API\Http\Middleware\RecovererMiddleware},
 * {@see \API\Http\Middleware\RouteDispatchMiddleware} and the controllers (via
 * {@see fromResult()}) all go through here.
 *
 * @see ApiResponse The success side.
 * @see ProblemDetailsProxy The document's shape.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final class ProblemResponse
{
    /**
     * Reason phrases for the status codes the domain surfaces through a
     * {@see \Shared\Exceptions\LeafContext} code.
     *
     * @var array<int, string>
     */
    private const array TITLES = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        409 => 'Conflict',
        422 => 'Unprocessable Entity',
        500 => 'Internal Server Error',
        503 => 'Service Unavailable',
    ];

    /**
     * A problem document with the given status and wording.
     *
     * Always JSON, never a FlatBuffer: an error must be readable by a client
     * that failed at the negotiation step.
     *
     * @param  int  $status  HTTP status for both the response and the `status`
     *                       member.
     * @param  string  $title  Short, stable summary of the problem kind.
     * @param  string|null  $detail  What went wrong on this occasion.
     * @param  string  $type  URI identifying the problem kind. `about:blank`
     *                        means the status code is the whole story.
     * @return ResponseInterface The outgoing HTTP response.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function make(
        int $status,
        string $title,
        ?string $detail = null,
        string $type = 'about:blank',
    ): ResponseInterface {
        $problem = new ProblemDetailsProxy(
            type: $type,
            title: $title,
            status: $status,
            detail: $detail,
        );

        return new Response(
            (string) json_encode($problem),
            $status,
            '',
            [HttpHeader::ContentType->value => MediaType::ProblemJson->value],
        );
    }

    /**
     * Maps a failed {@see Result} to a problem response, using the status code
     * and message carried by its {@see \Shared\Exceptions\LeafContext}.
     *
     * Anything outside the 4xx/5xx range — including a missing context — becomes
     * a 500: a failure that did not name a client-facing code is this server's
     * problem, not the caller's.
     *
     * @param  Result<mixed>  $result  The failed result to render.
     * @return ResponseInterface The outgoing HTTP response.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function fromResult(Result $result): ResponseInterface
    {
        $context = Leaf::getError($result->getErrorId());

        $status = $context !== null && $context->code >= 400 && $context->code <= 599
            ? $context->code
            : 500;

        return self::make(
            $status,
            self::TITLES[$status] ?? 'Error',
            $context?->message,
        );
    }
}
