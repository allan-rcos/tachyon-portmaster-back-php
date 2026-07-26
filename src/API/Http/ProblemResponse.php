<?php

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
 */
final class ProblemResponse
{
    /**
     * Reason phrases for the status codes the domain surfaces through a
     * {@see \Shared\Exceptions\LeafContext} code.
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
     * @param  Result<mixed>  $result
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
