<?php

/**
 * Content Negotiation Middleware.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Http\Middleware;

use API\Http\ContentKind;
use API\Http\HttpHeader;
use API\Http\RequestAttributes;
use API\Negociation\IAcceptsStrategy;
use API\Negociation\IAcceptsStrategyManager;
use API\Negociation\IContentTypeStrategy;
use API\Negociation\IContentTypeStrategyManager;
use Infra\Logging\ILogger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Content-negotiation middleware: the one place `header → strategy` is decided.
 *
 * It does not touch request or response bodies. It reads the two headers,
 * resolves each to a strategy and records them for the request — the request
 * body's format from `Content-Type`, the response's from `Accept`. The two are
 * independent: a caller may POST JSON but ask for binary back.
 *
 * The four strategies arrive built, from the provider, like every other
 * collaborator in the system: none of them holds state, so there is one of each
 * per worker and this only ever hands out a reference. What it never gets is a
 * way to *use* them — the two contexts arrive as **managers**, so this can set
 * the choice and cannot act on it, while the controllers can act on it and
 * cannot change it.
 *
 * On the way out it does one more thing: it puts the `Content-Type` on the
 * response. The strategies never answer what they are — see
 * {@see \API\Negociation\IAcceptsStrategy} — and this is the only place that
 * both read `Accept` and sees the finished answer. It also copies the two
 * negotiated formats into the logger context, so the (outer) logging middleware
 * reports them alongside the request.
 *
 * @see ContentKind What parses each header, and names the answer.
 * @see RequestAttributes Where the decision is kept.
 * @uses ILogger Reports the two formats alongside the request.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final class ContentNegotiationMiddleware implements MiddlewareInterface
{
    /**
     * @var ILogger This middleware's own channel.
     */
    private ILogger $logger;

    /**
     * @param  IContentTypeStrategyManager  $contentType  Records how to read the body.
     * @param  IAcceptsStrategyManager  $accepts  Records how to write the answer.
     * @param  IContentTypeStrategy  $jsonBody  Recorded for a JSON request body.
     * @param  IContentTypeStrategy  $flatbufferBody  Recorded for a binary one.
     * @param  IAcceptsStrategy  $jsonAnswer  Recorded for a JSON response.
     * @param  IAcceptsStrategy  $flatbufferAnswer  Recorded for a binary one.
     * @param  ILogger  $logger  Narrowed to the `http` channel.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private readonly IContentTypeStrategyManager $contentType,
        private readonly IAcceptsStrategyManager $accepts,
        private readonly IContentTypeStrategy $jsonBody,
        private readonly IContentTypeStrategy $flatbufferBody,
        private readonly IAcceptsStrategy $jsonAnswer,
        private readonly IAcceptsStrategy $flatbufferAnswer,
        ILogger $logger,
    ) {
        $this->logger = $logger->withChannel('http');
    }

    /**
     * Records both strategies before the stack runs, and logs them after.
     *
     * Negotiation cannot fail: an unreadable or absent header resolves to the
     * kind's default rather than a 406, so a client that sends no headers at all
     * still gets a usable answer.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @param  RequestHandlerInterface  $handler  The rest of the stack.
     * @return ResponseInterface The inner response, untouched.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestKind  = ContentKind::fromContentType($request->getHeaderLine(HttpHeader::ContentType->value));
        $responseKind = ContentKind::fromAccept($request->getHeaderLine(HttpHeader::Accept->value));

        $this->contentType->setStrategy(
            $requestKind === ContentKind::Json ? $this->jsonBody : $this->flatbufferBody,
        );

        $this->accepts->setStrategy(
            $responseKind === ContentKind::Json ? $this->jsonAnswer : $this->flatbufferAnswer,
        );

        $response = $handler->handle($request);

        $this->logger->setContext(RequestAttributes::RequestContentStrategy->value, $requestKind->value);
        $this->logger->setContext(RequestAttributes::ResponseAcceptsStrategy->value, $responseKind->value);

        return self::labelled($response, $responseKind);
    }

    /**
     * Names the bytes the stack just produced.
     *
     * Here rather than where the response is built, because this is where the
     * `Accept` header was read: nothing downstream has to re-derive the answer,
     * and nothing downstream can get it wrong. A response that named itself is
     * left alone — that is {@see \API\Http\ProblemResponse}'s last-resort
     * document, which is JSON no matter what was negotiated — and a `204` is
     * left bare, having no content to describe.
     *
     * @param  ResponseInterface  $response  What the stack answered.
     * @param  ContentKind  $kind  The format `Accept` resolved to.
     * @return ResponseInterface The same response, labelled.
     *
     * @copyright 2026 Tachyon
     */
    private static function labelled(ResponseInterface $response, ContentKind $kind): ResponseInterface
    {
        if ($response->getStatusCode() === 204 || $response->hasHeader(HttpHeader::ContentType->value)) {
            return $response;
        }

        return $response->withHeader(
            HttpHeader::ContentType->value,
            $kind->mediaType($response->getStatusCode())->value,
        );
    }
}
