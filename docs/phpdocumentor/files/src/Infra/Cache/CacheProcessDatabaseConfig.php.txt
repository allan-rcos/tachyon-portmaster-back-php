<?php

/**
 * Cache Process Database Config.
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

use Infra\Config\CacheLimits;

/**
 * What makes one logical database inside the shared cache.
 *
 * The cache process holds a single pair of tables for the whole application, and
 * this is how a caller carves a private slice out of it: every key a database
 * writes is prefixed with {@see $key}, so two databases can use the same suffix
 * and never collide, and a {@see ICacheProcessDatabase::clean()} with no suffix
 * drops that slice and nothing else.
 *
 * Fixed once, in {@see \Infra\Interno\InfraProvider}, and held for the worker's
 * lifetime. The per-operation counterpart is {@see CacheProcessEntryConfig},
 * which arrives with the call rather than with the database — and which is where
 * a single write overrides the TTL declared here.
 *
 * More settings belong here as the cache grows: this is the "what kind of
 * database is this" object, and it is deliberately a value object rather than a
 * pair of scalars so adding one is not a signature change everywhere.
 *
 * @see ICacheProcessDatabase What this configures.
 * @see IOpenSwooleCacheProcessAdapter::database() What turns this into one.
 * @see CacheProcessEntryConfig The per-operation half.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class CacheProcessDatabaseConfig
{
    /**
     * @param  string  $key  Prefix every key in this database carries. It is the
     *                       slice, so it has to be unique across databases —
     *                       `view`, `permission`, `marker` — and short, because
     *                       it is charged against
     *                       {@see CacheLimits::LOGICAL_KEY_MAX_BYTES} on every
     *                       entry.
     * @param  int  $ttlSeconds  How long an entry written here lives, in seconds.
     *                           {@see CacheLimits::TTL_FOREVER} means it never
     *                           expires, which is what a registry filled from
     *                           code wants. A single write can override this
     *                           through
     *                           {@see CacheProcessEntryConfig::$ttlSeconds}.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public string $key,
        public int $ttlSeconds = CacheLimits::VIEW_TTL_SECONDS,
    ) {
    }
}
