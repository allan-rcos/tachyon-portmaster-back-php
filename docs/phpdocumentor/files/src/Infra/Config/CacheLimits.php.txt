<?php

/**
 * Read Cache Limits.
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
 * How long a cached read stays valid.
 *
 * A namespace rather than a value object: there is nothing to construct and
 * nothing to inject, and unlike {@see DatabaseConfig} it reads no environment.
 * The lifetime of a cache entry is a policy decision rather than a deployment
 * one.
 *
 * The payload ceiling is deliberately **not** here — it belongs to
 * {@see \Infra\Repository\Interno\SqlViewCacheRepository}, being an artefact of
 * the column that implementation writes into. Neither is the row capacity, which
 * `max_heap_table_size` enforces and the engine reports reaching.
 *
 * @see \Infra\Repository\IViewCacheRepository What applies this.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class CacheLimits
{
    /**
     * @var int How long a cached query result keeps being served, in seconds.
     *
     * Short on purpose. The cache here absorbs bursts of repeated reads; it does
     * not stand in for the database. Every write invalidates the group it
     * touched, so the window in which stale data can be served is the gap
     * between two reads of an untouched group, not this TTL.
     *
     * It is also the only thing bounding staleness *across* groups, which is
     * where the number earns being this low: a container write drops the
     * `container` group and deliberately leaves `metrics` alone, so the
     * occupancy panel is behind by at most this long. See
     * {@see \Infra\Repository\ViewCacheGroup} for why one write never reaches
     * into another group.
     */
    public const int TTL_SECONDS = 30;
}
