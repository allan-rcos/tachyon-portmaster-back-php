<?php

declare(strict_types=1);

namespace API\Http\Middleware;

use API\Http\ProblemResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Infra\Logging\ILogger;
use Throwable;

/**
 * Catches any {@see Throwable} thrown by inner middlewares/controllers and turns
 * it into a standardized {@see ProblemResponse}.
 *
 * It sits just inside the logging middleware: no exception ever escapes to the
 * server, and the logging middleware simply sees (and logs) the resulting error
 * response like any other. The original throwable is logged here so its details
 * are not lost once it has been converted to a response.
 */
final class RecovererMiddleware implements MiddlewareInterface
{
    private ILogger $logger;

    public function __construct(ILogger $logger)
    {
        $this->logger = $logger->withChannel('recoverer');
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $e) {
            $this->logger->error('Unhandled error while processing request', [
                'error'     => $e->getMessage(),
                'exception' => $e::class,
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]);

            return ProblemResponse::make(
                500,
                'Internal Server Error',
                'An unexpected error occurred while processing the request.',
            );
        }
    }
}
