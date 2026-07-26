<?php

declare(strict_types=1);

namespace API\Http\Middleware;

use API\Http\HttpHeader;
use API\Http\RequestAttributes;
use Domain\ID\ISequentialIdGenerator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Infra\Logging\ILogger;

/**
 * Generates a unique request id and threads it through the request lifecycle.
 *
 * The id is stored in the coroutine context (via
 * {@see RequestAttributes::RequestId}), bound to the logger context so every log
 * line carries it, and echoed back to the client through the `X-Request-Id`
 * response header.
 *
 * It takes a {@see ISequentialIdGenerator}, not a database one: a request id is
 * never persisted, so it has no business consuming the Snowflake sequence —
 * which exists to keep a BIGINT primary-key index append-only. What it does need
 * is chronological sortability, so logs and traces line up by id alone.
 */
final class RequestIdMiddleware implements MiddlewareInterface
{
    private ILogger $logger;

    public function __construct(
        private readonly ISequentialIdGenerator $idGenerator,
        ILogger $logger,
    ) {
        $this->logger = $logger->withChannel('request-id');
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestId = $this->idGenerator->generate();

        RequestAttributes::RequestId->write($requestId);
        $this->logger->setContext(RequestAttributes::RequestId->value, (string) $requestId);

        $response = $handler->handle($request);

        return $response->withHeader(HttpHeader::RequestId->value, (string) $requestId);
    }
}
