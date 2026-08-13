<?php

/**
 * Logging Middleware.
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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Infra\Logging\ILogger;

/**
 * Structured access logging around the request lifecycle.
 *
 * It logs the inbound request and the outbound response, the latter with the
 * final HTTP status code and wall-clock duration. Negotiated content kinds,
 * request id, etc. ride along automatically via the coroutine-scoped logger
 * context.
 *
 * It does not catch exceptions, and {@see RecovererMiddleware} sits *outside*
 * it — so a request that throws is logged by that middleware as an error and
 * never reaches the "Request handled" line below. An access log line therefore
 * means the request completed, recovered 500s excluded.
 *
 * @see RecovererMiddleware Why a throwable never reaches here.
 * @uses ILogger The destination.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final class LoggingMiddleware implements MiddlewareInterface
{
    /**
     * @var ILogger This middleware's own channel.
     */
    private ILogger $logger;

    /**
     * @param  ILogger  $logger  Narrowed to the `http` channel.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(ILogger $logger)
    {
        $this->logger = $logger->withChannel('http');
    }

    /**
     * Logs the request at debug, then the response at info.
     *
     * The split is deliberate: the inbound line is diagnostic detail, while the
     * outbound one — method, path, status and duration — is the access log, and
     * a server running at `INFO` still gets one line per request.
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
