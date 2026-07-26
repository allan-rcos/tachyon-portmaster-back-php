<?php

declare(strict_types=1);

namespace API\Http\Middleware;

use API\Http\ContentKind;
use API\Http\HttpHeader;
use API\Http\RequestAttributes;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Infra\Logging\ILogger;

/**
 * FlatBuffer content-negotiation middleware.
 *
 * Its only job is to read the negotiation headers and record the decision in
 * the coroutine context — it does not touch request or response bodies. The
 * request body format is taken from `Content-Type` and the desired response
 * format from `Accept` (they are independent: a caller may POST JSON and ask
 * for binary back, or vice-versa). The Fbs proxies then read these kinds back
 * in {@see \API\Fbs\Contracts\IFbsProxy::fromStream()} /
 * {@see \API\Fbs\Contracts\IFbsProxy::toStream()} inside the controller.
 *
 * After the request is handled it copies the negotiated kinds into the logger
 * context so the (outer) logging middleware reports both formats.
 */
final class FlatBufferNegotiationMiddleware implements MiddlewareInterface
{
    private ILogger $logger;

    public function __construct(ILogger $logger)
    {
        $this->logger = $logger->withChannel('http');
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestKind  = ContentKind::fromContentType($request->getHeaderLine(HttpHeader::ContentType->value));
        $responseKind = ContentKind::fromAccept($request->getHeaderLine(HttpHeader::Accept->value));

        RequestAttributes::RequestContentKind->write($requestKind);
        RequestAttributes::ResponseContentKind->write($responseKind);

        $response = $handler->handle($request);

        $this->logger->setContext(RequestAttributes::RequestContentKind->value, $requestKind->value);
        $this->logger->setContext(RequestAttributes::ResponseContentKind->value, $responseKind->value);

        return $response;
    }
}
