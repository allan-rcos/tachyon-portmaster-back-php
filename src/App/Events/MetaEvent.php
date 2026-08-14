<?php

/**
 * Meta Event Enum.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App\Events;

/**
 * Something that happened *while* answering a request, rather than something the
 * request asked for.
 *
 * These are about **how** an answer was produced, never about what it says. A
 * use case reports one and carries on; nothing branches on one having been
 * reported, and removing every emit would change no response body. That is what
 * separates a meta event from a domain event, which this is not: there is no
 * subscriber, no ordering, and no delivery guarantee to speak of.
 *
 * The one consumer today is
 * {@see \API\Http\Middleware\CacheHeaderMiddleware}, which turns
 * {@see ViewCacheHit} into a response header so a caller — and the integration
 * suite — can see which reads were served from the cache.
 *
 * @see IMetaEventStack Where one is reported and read back.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
enum MetaEvent: string
{
    /**
     * A read was answered from the view cache instead of from the database.
     *
     * Emitted by the list use cases at the moment they return a hit, so it says
     * the *response* came from the cache — not merely that the cache was
     * consulted, which every one of them does.
     */
    case ViewCacheHit = 'view-cache-hit';
}
