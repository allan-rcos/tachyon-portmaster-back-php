<?php

/**
 * Cache Limits.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra\Config;

/**
 * How long a cached entry stays valid, and how long a key may be.
 *
 * A namespace rather than a value object: there is nothing to construct and
 * nothing to inject, and unlike {@see CacheConfig} it reads no environment. What
 * lives here is policy — a decision about correctness — while how much RAM the
 * cache may take and how often it is swept is deployment, and lives there.
 *
 * The TTLs here are **defaults**, applied by whichever
 * {@see \Infra\Cache\CacheProcessDatabaseConfig} names them. A single write can
 * still override its own through
 * {@see \Infra\Cache\CacheProcessEntryConfig::$ttlSeconds}, which is what markers
 * do.
 *
 * @see \Infra\Cache\ICacheProcessDatabase What applies these.
 * @see CacheConfig The deployment half.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class CacheLimits
{
    /**
     * @var int A cached query result keeps being served for this many seconds.
     *
     * Short on purpose. The cache absorbs bursts of repeated reads; it does not
     * stand in for the database. Every write invalidates the group it touched,
     * so the window in which stale data can be served is the gap between two
     * reads of an untouched group, not this TTL.
     *
     * It is also the only thing bounding staleness *across* groups, which is
     * where the number earns being this low: a container write drops the
     * `container` group and deliberately leaves `metrics` alone, so the occupancy
     * panel is behind by at most this long. See
     * {@see \Infra\Repository\ViewCacheGroup} for why one write never reaches
     * into another group.
     */
    public const int VIEW_TTL_SECONDS = 30;

    /**
     * @var int Fallback lifetime for a marker that does not state its own.
     *
     * Markers are the one family whose lifetime belongs to the caller rather
     * than to this file: a refresh-token marker has to outlive exactly the token
     * it tracks, and the next marker written will have some other reason to
     * expire when it does. {@see \Infra\Repository\IMarkerRepository::set()}
     * therefore takes the TTL as an argument and passes it through, and this
     * constant only covers a write that names none.
     *
     * Fourteen days, matching the `APP_REFRESH_TTL` default, so the fallback errs
     * towards keeping a revocation rather than forgetting one. Nothing reaches
     * it today — the one caller always states its own — and that is the point of
     * it being generous rather than tight.
     */
    public const int MARKER_TTL_SECONDS = 1209600;

    /**
     * @var int Never-expires sentinel, for a database whose entries are the
     *          catalogue itself.
     *
     * The permission and marker-group registries are filled from code at
     * `WorkerStart` and are correct for as long as the process lives; expiring
     * them would mean a worker forgetting what it is allowed to do. The sweeper
     * skips any entry carrying this.
     */
    public const int TTL_FOREVER = 0;

    /**
     * @var int Longest logical key that gets stored, in bytes.
     *
     * Keys are short by construction — a listing's filters and its cursor
     * position — but a caller is free to send a search term of any length, and
     * that term is part of the query's identity and therefore part of the key.
     * Truncating it would make two different searches share an entry, so an
     * over-long key means the query is not cached instead. That is also what
     * keeps a `?limit=100000` from occupying an entry it would never be read
     * from again.
     *
     * It is not the `OpenSwoole\Table` key limit, which is 63 bytes and is dealt
     * with by hashing — see
     * {@see \Infra\Cache\Interno\TableCacheProcessDatabase}. This is the width of
     * the column the *logical* key is kept in so that a prefix clean can match
     * against it.
     */
    public const int LOGICAL_KEY_MAX_BYTES = 191;
}
