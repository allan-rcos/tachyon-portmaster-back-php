<?php

declare(strict_types=1);

namespace API\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Infra\Logging\ILogger;

/**
 * Structured access logging around the request lifecycle.
 *
 * It logs the inbound request and the outbound response, the latter with the
 * final HTTP status code and wall-clock duration. It does not catch
 * exceptions: {@see RecovererMiddleware} sits just inside it and converts any
 * throwable into a response, so this middleware always observes a normal
 * response (including recovered errors). Negotiated content kinds, request id,
 * etc. ride along automatically via the coroutine-scoped logger context.
 */
final class LoggingMiddleware implements MiddlewareInterface
{
    private ILogger $logger;

    public function __construct(ILogger $logger)
    {
        $this->logger = $logger->withChannel('http');
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = $request->getMethod();
        $uri    = $request->getUri()->getPath();
        $server = $request->getServerParams();

        $this->logger->debug('Incoming request', [
            'method'      => $method,
            'uri'         => $uri,
            'client_ip'   => $server['remote_addr'] ?? 'unknown',
            'client_port' => $server['remote_port'] ?? 'unknown',
        ]);

        $startedAt = microtime(true);

        $response = $handler->handle($request);

        $this->logger->info('Request handled', [
            'method'      => $method,
            'uri'         => $uri,
            'status'      => $response->getStatusCode(),
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ]);

        return $response;
    }
}
