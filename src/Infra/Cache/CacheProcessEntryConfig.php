<?php

/**
 * Cache Process Entry Config.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra\Cache;

/**
 * What makes one operation differ from the database's defaults.
 *
 * The per-call half of the cache's configuration, where
 * {@see CacheProcessDatabaseConfig} is the per-database half. It exists as an
 * object rather than as a bare `?string $suffix` for one concrete reason:
 * markers. {@see \Infra\Repository\IMarkerRepository::set()} is handed a TTL by
 * whoever writes the marker — a refresh-token marker has to outlive exactly the
 * token it tracks — and today's single caller is not a promise about the next
 * one. A suffix string could not carry that, and a third parameter on
 * {@see ICacheProcessDatabase::put()} would have to be repeated on a method that
 * has no use for it.
 *
 * Both fields are optional and both fall back to the database: no suffix means
 * the database's own key, and no TTL means
 * {@see CacheProcessDatabaseConfig::$ttlSeconds}. Passing nothing at all is the
 * common case, which is why every method takes this parameter as nullable.
 *
 * @see ICacheProcessDatabase Where this is passed.
 * @see CacheProcessDatabaseConfig The per-database half.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class CacheProcessEntryConfig
{
    /**
     * @param  string|null  $suffix  Appended to the database's key to make the
     *                               entry's own. `null` addresses the database's
     *                               bare key, which is what a registry holding
     *                               its whole catalogue in one entry uses. On
     *                               {@see ICacheProcessDatabase::clean()} this is
     *                               read as a **prefix** rather than as a whole
     *                               key, so `container:` drops every entry filed
     *                               under it.
     * @param  int|null  $ttlSeconds  Lifetime for this one entry, in seconds,
     *                                overriding the database's default. `null`
     *                                inherits it. Read by
     *                                {@see ICacheProcessDatabase::put()} and by
     *                                nothing else — a TTL on a read or on a clean
     *                                would have nothing to apply to.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ?string $suffix = null,
        public ?int $ttlSeconds = null,
    ) {
    }
}
