<?php

/**
 * Cache Header Middleware.
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

use API\Http\HttpHeader;
use App\Events\IMetaEventStack;
use App\Events\MetaEvent;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Says on the response whether the body came out of the view cache.
 *
 * The use cases set no headers and the middlewares know nothing about caching;
 * {@see IMetaEventStack} is the seam between them. A list use case reports
 * {@see MetaEvent::ViewCacheHit} as it returns a hit, and this reads that back
 * once the stack has unwound.
 *
 * It flushes on the way in rather than on the way out, so a request starts clean
 * by construction instead of by trusting the runtime to have discarded the last
 * one.
 *
 * Last in the stack, immediately outside {@see RouteDispatchMiddleware}, so it
 * is the innermost thing that sees both the finished response and the events
 * that produced it.
 *
 * @see \API\ApiRegister Where the stack's order is set.
 * @see docs/adr/0010-read-cache-in-a-memory-table.md Why a hit is made observable at all.
 * @uses IMetaEventStack Read on the way out, flushed on the way in.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class CacheHeaderMiddleware implements MiddlewareInterface
{
    /**
     * @var string How this cache names itself in {@see HttpHeader::Cache}.
     *
     * RFC 9211 makes the field a list of caches, each identified by name, so
     * that a CDN in front of this one appends its own entry rather than
     * overwriting. The name is what tells the two apart when it does.
     */
    private const string CACHE_NAME = 'Portmaster';

    /**
     * @var string The whole field value written when the view cache answered.
     *
     * There is no matching miss value: the header is simply absent. Emitting
     * `fwd=miss` would be the fuller RFC vocabulary, but it needs the middleware
     * to know that a cache was *consulted* and missed — which is a second event,
     * and a distinction none of the reads that skip the cache entirely could
     * make today.
     */
    public const string HIT = self::CACHE_NAME.'; hit';

    /**
     * @param  IMetaEventStack  $events  The per-request stack; the same instance
     *                                   the use cases were given, since what it
     *                                   holds is scoped by the coroutine rather
     *                                   than by the object.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IMetaEventStack $events,
    ) {
    }

    /**
     * Clears the stack, runs the request, and marks the response if a cached
     * read answered it.
     *
     * A throwable unwinds past this line, so a recovered 500 carries no header —
     * correct, since a response the recoverer invented was not served from
     * anything.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @param  RequestHandlerInterface  $handler  The rest of the stack.
     * @return ResponseInterface The inner response, plus `Cache-Status` when
     *                           the body came from the view cache.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->events->flush();

        $response = $handler->handle($request);

        if (!$this->events->captured(MetaEvent::ViewCacheHit)) {
            return $response;
        }

        return $response->withHeader(HttpHeader::Cache->value, self::HIT);
    }
}
