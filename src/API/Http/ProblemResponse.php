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

use API\Negociation\DTO\Common\ProblemDetailsX;
use API\Negociation\DTO\Common\ProblemDetailsXFactory;
use API\Negociation\IAcceptsStrategy;
use OpenSwoole\Core\Psr\Response;
use Psr\Http\Message\ResponseInterface;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * Builds standardized error responses as RFC 7807 problem details.
 *
 * `ProblemDetails` is a table of the published schema like any other, so an
 * error is rendered by the same {@see IAcceptsStrategy} as a success: a caller
 * that asked for binary gets the problem document in binary. What stays special
 * is the media type — `application/problem+json` on the JSON branch, so a
 * client switching on it keeps working — and that follows from the status code,
 * in {@see \API\Http\ContentKind::mediaType()}.
 *
 * An error raised before negotiation ran — anything
 * {@see \API\Http\Middleware\RecovererMiddleware} catches from outside the
 * negotiation middleware — still answers, because the strategy context falls
 * back to JSON when nothing was recorded.
 *
 * @see ApiResponse The success side.
 * @see ProblemDetailsX The document's shape.
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
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
    ];

    /**
     * A problem document with the given status and wording.
     *
     * Answers a response and not a {@see Result}, unlike
     * {@see ApiResponse::body()}: this is what a caller falls back *to*, so a
     * failure here would have nowhere to go. It renders one anyway — see the
     * last resort in the body below.
     *
     * @param  IAcceptsStrategy  $accepts  Renders the document; in practice the
     *                                     request's {@see \API\Negociation\Interno\AcceptsStrategyContext}.
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
        IAcceptsStrategy $accepts,
        int $status,
        string $title,
        ?string $detail = null,
        string $type = 'about:blank',
    ): ResponseInterface {
        $problem = new ProblemDetailsXFactory(new ProblemDetailsX(
            type: $type,
            title: $title,
            status: $status,
            detail: $detail,
        ));

        $body = $accepts->toStream($problem);

        // The one response in the system that does not hand a rendering failure
        // back to its caller: this *is* what a caller falls back to, so it must
        // answer something even when the negotiated format is what broke. It
        // names itself, too — the document is hand-written JSON whatever was
        // negotiated, and the middleware leaves a label already set alone.
        if (!$body->isSuccess()) {
            return new Response(
                (string) json_encode(['type' => $type, 'title' => $title, 'status' => $status, 'detail' => $detail]),
                $status,
                '',
                [HttpHeader::ContentType->value => MediaType::ProblemJson->value],
            );
        }

        return new Response((string) $body->getValue(), $status);
    }

    /**
     * Maps a failed {@see Result} to a problem response, using the status code
     * and message carried by its {@see \Shared\Exceptions\LeafContext}.
     *
     * Anything outside the 4xx/5xx range — including a missing context — becomes
     * a 500: a failure that did not name a client-facing code is this server's
     * problem, not the caller's.
     *
     * @param  IAcceptsStrategy  $accepts  Renders the document.
     * @param  Result<mixed>  $result  The failed result to render.
     * @return ResponseInterface The outgoing HTTP response.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function fromResult(IAcceptsStrategy $accepts, Result $result): ResponseInterface
    {
        $context = Leaf::getError($result->getErrorId());

        $status = $context !== null && $context->code >= 400 && $context->code <= 599
            ? $context->code
            : 500;

        return self::make(
            $accepts,
            $status,
            self::TITLES[$status] ?? 'Error',
            $context?->message,
        );
    }
}
