<?php

/**
 * Cache Process Config.
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
 * How much memory the cache process is allowed, and how often it sweeps.
 *
 * The deployment half of the cache's settings. The policy half — how long an
 * entry stays valid — is {@see CacheLimits}, which reads no environment: a TTL
 * is a decision about correctness, while everything here is a decision about the
 * machine it runs on.
 *
 * Read from the environment at bootstrap like every other config in this
 * namespace, but consumed in an unusual place: {@see \Infra\OpenSwooleExtension}
 * takes it in the **global** context, before `$server->start()` forks, because
 * the tables it sizes have to exist before there are workers to share them.
 * Nothing below that reads it.
 *
 * Every field has a default, so a missing variable yields a working local
 * configuration rather than a boot failure.
 *
 * @see \Infra\OpenSwooleExtension What applies this, pre-fork.
 * @see CacheLimits The policy half.
 * @see DatabaseConfig The same pattern, for the connection pool.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
readonly class CacheConfig
{
    /**
     * @param  int  $entries  How many entries the cache holds. OpenSwoole rounds
     *                        this up to a power of two and allocates conflict
     *                        slots on top, so the real cost is roughly
     *                        `entries * payloadBytes * 2`. The default is about
     *                        261 MB, which is the same order as the
     *                        `--max-heap-table-size=256M` the MEMORY table used
     *                        to need — the RAM changed owner, not size.
     * @param  int  $payloadBytes  Width of the payload column, in bytes. An
     *                             `OpenSwoole\Table` string column is fixed-width
     *                             and padded, so this is charged on every entry
     *                             whatever it holds, and a view serialising
     *                             larger than it is simply not cached. 16 KB is
     *                             measured rather than guessed: the widest view
     *                             is a default page of container summaries, at
     *                             ~11.3 KB under igbinary.
     * @param  int  $sweepIntervalMs  Milliseconds between two runs of the
     *                                sweeper. Entries are already filtered by
     *                                expiry on read, so this governs when memory
     *                                comes back, never whether a stale entry can
     *                                be served.
     * @param  float  $highWater  Occupancy, between 0 and 1, at which the sweeper
     *                            starts evicting entries that have not expired
     *                            yet. `OpenSwoole\Table` has no LRU of its own and
     *                            a write to a full table simply fails, so
     *                            something has to make room before that happens.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public int $entries = 8192,
        public int $payloadBytes = 16384,
        public int $sweepIntervalMs = 1000,
        public float $highWater = 0.9,
    ) {
    }
}
