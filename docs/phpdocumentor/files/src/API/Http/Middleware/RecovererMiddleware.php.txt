<?php

/**
 * Recoverer Middleware.
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
use API\Http\ProblemResponse;
use API\Http\RequestAttributes;
use API\Negociation\IAcceptsStrategy;
use Infra\Logging\ILogger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Catches any {@see Throwable} thrown by inner middlewares/controllers and turns
 * it into a standardized {@see ProblemResponse}.
 *
 * The **outermost** middleware in the stack, so no exception from anything
 * inside it — including the logging middleware — can escape to the server. The
 * original throwable is logged here so its details are not lost once it has been
 * converted to a response.
 *
 * Being outside the logging middleware has a consequence worth knowing: a
 * request that throws never reaches that middleware's "Request handled" line,
 * so a 500 recovered here appears in the log as this class's error entry and
 * not as an access-log line.
 *
 * It is also outside the negotiation middleware, which has two consequences.
 * A throwable raised before negotiation ran is answered in JSON — the strategy
 * context's fallback, and the safe direction: a client that failed at the
 * negotiation step can still read the document explaining why. And nothing has
 * labelled the response by the time it gets here, so this reads `Accept` itself
 * for the one answer that never passes through the middleware that normally
 * would.
 *
 * @see ProblemResponse What the throwable becomes.
 * @uses ILogger Records the throwable before it is discarded.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final class RecovererMiddleware implements MiddlewareInterface
{
    /**
     * @var ILogger This middleware's own channel.
     */
    private ILogger $logger;

    /**
     * @param  IAcceptsStrategy  $accepts  Renders the problem document.
     * @param  ILogger  $logger  Narrowed to the `recoverer` channel.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private readonly IAcceptsStrategy $accepts,
        ILogger $logger,
    ) {
        $this->logger = $logger->withChannel('recoverer');
    }

    /**
     * Runs the inner stack, converting anything it throws into a 500.
     *
     * The response says only that something unexpected happened: the class,
     * file and line go to the log, where the request id ties them back to this
     * caller's report.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @param  RequestHandlerInterface  $handler  The rest of the stack.
     * @return ResponseInterface The inner response, or a 500 problem document.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
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

            $response = ProblemResponse::make(
                $this->accepts,
                500,
                'Internal Server Error',
                'An unexpected error occurred while processing the request.',
            );

            if ($response->hasHeader(HttpHeader::ContentType->value)) {
                return $response;
            }

            // The document was rendered by the strategy context, which falls
            // back to JSON when the negotiation middleware — inside this one —
            // never ran. Reading `Accept` in that case would promise a format
            // the bytes are not in, so the label follows what was recorded.
            $kind = RequestAttributes::ResponseAcceptsStrategy->read() === null
                ? ContentKind::Json
                : ContentKind::fromAccept($request->getHeaderLine(HttpHeader::Accept->value));

            return $response->withHeader(HttpHeader::ContentType->value, $kind->mediaType(500)->value);
        }
    }
}
