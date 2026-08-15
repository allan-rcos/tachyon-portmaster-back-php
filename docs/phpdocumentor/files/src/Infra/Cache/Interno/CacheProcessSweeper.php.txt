<?php

/**
 * Cache Process Sweeper.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra\Cache\Interno;

use Infra\Config\CacheConfig;
use Infra\Config\CacheLimits;
use Infra\Logging\ILogger;
use OpenSwoole\Table;
use OpenSwoole\Timer;

/**
 * The body of the process that keeps the cache alive.
 *
 * Added to the server by {@see OpenSwooleCacheProcessAdapter::attach()}, in the
 * global context, so it is a sibling of the workers rather than one of them:
 * it holds no request, serves no route and exists only to do the two things the
 * tables cannot do for themselves.
 *
 *  1. **Expire.** `OpenSwoole\Table` has no notion of time.
 *     {@see TableCacheProcessDatabase::get()} already refuses an entry past its
 *     expiry, so this is about reclaiming memory rather than about correctness —
 *     which is why running it on a timer, instead of on every write, is safe.
 *  2. **Evict.** `OpenSwoole\Table` has no LRU either. A write to a full table
 *     just fails, and a cache that can no longer store anything is invisible
 *     from the outside, so something has to make room before that happens.
 *     Above {@see CacheConfig::$highWater} occupancy, the entries closest to
 *     expiring go first.
 *
 * Only `index` is iterated, never `store`: iterating a table materialises every
 * row as a PHP array, and `store` is where the 16 KB payloads are. See
 * {@see TableCacheProcessDatabase}.
 *
 * If this process dies, OpenSwoole restarts it. A gap in sweeping costs RAM
 * until it comes back, and never a stale answer.
 *
 * @see OpenSwooleCacheProcessAdapter What attaches this.
 * @see TableCacheProcessDatabase What writes what this reclaims.
 * @see docs/adr/0011-cache-em-processo-openswoole.md Why the cache lives in a process.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class CacheProcessSweeper
{
    /**
     * @var ILogger Channelled copy, so a sweep is attributable to the sweeper.
     */
    private ILogger $logger;

    /**
     * @param  Table  $index  Logical key and expiry. The only table scanned.
     * @param  Table  $store  Payloads, deleted alongside their index entries.
     * @param  CacheConfig  $config  How often to run, and how full is too full.
     * @param  ILogger  $logger  Rebound to the `cache-process-sweeper` channel.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private Table $index,
        private Table $store,
        private CacheConfig $config,
        ILogger $logger,
    ) {
        $this->logger = $logger->withChannel('cache-process-sweeper');
    }

    /**
     * Starts the timer. Runs as the process's body and never returns.
     *
     * The process is created with coroutines enabled, which is what gives it an
     * event loop for {@see Timer} to run on; without that this would return
     * immediately and the process would exit.
     *
     * @copyright 2026 Tachyon
     */
    public function __invoke(): void
    {
        $this->logger->info('Cache sweeper started', [
            'entries' => (string) $this->index->getSize(),
            'interval_ms' => (string) $this->config->sweepIntervalMs,
        ]);

        Timer::tick($this->config->sweepIntervalMs, function (): void {
            $this->sweep();
        });
    }

    /**
     * One pass: drop what has expired, then evict if the table is still too full.
     *
     * Entries carrying {@see CacheLimits::TTL_FOREVER} are skipped by both
     * halves. They are the registries, filled from code and correct for as long
     * as the process lives, so evicting one would mean a worker forgetting what
     * it is allowed to do.
     *
     * @copyright 2026 Tachyon
     */
    private function sweep(): void
    {
        $now = time();

        /** @var list<string> $expired */
        $expired = [];
        /** @var array<string, int> $living Digest to expiry, for the eviction pass. */
        $living = [];

        foreach ($this->index as $digest => $row) {
            if (!is_string($digest) || !is_array($row)) {
                continue;
            }

            $expires = $row['expires'] ?? CacheLimits::TTL_FOREVER;
            if (!is_int($expires)) {
                continue;
            }

            if ($expires === CacheLimits::TTL_FOREVER) {
                // A registry: no candidate for either pass. See {@see sweep()}.
                continue;
            }

            if ($expires <= $now) {
                $expired[] = $digest;

                continue;
            }

            $living[$digest] = $expires;
        }

        // Collected first, deleted after: mutating the table while iterating it
        // would be doing so underneath another worker's concurrent write.
        foreach ($expired as $digest) {
            $this->drop($digest);
        }

        $this->evict($living);
    }

    /**
     * Makes room when expiry alone did not.
     *
     * Closest to expiring goes first. That is not an LRU — the table records no
     * access — but it is the ordering that discards the entries with the least
     * remaining value.
     *
     * Occupancy is read from the table rather than from `$living`, because the
     * registries occupy slots too and counting only the expirable half would let
     * them fill it without the ceiling ever noticing. The surplus still comes out
     * of `$living` alone, since a registry is never a candidate.
     *
     * @param  array<string, int>  $living  Digest to expiry, for entries that
     *                                      survived the expiry pass.
     *
     * @copyright 2026 Tachyon
     */
    private function evict(array $living): void
    {
        $capacity = $this->index->getSize();
        $ceiling = (int) ($capacity * $this->config->highWater);

        // What the table holds, registries included; only $living can be taken.
        $occupied = $this->index->count();
        if ($occupied <= $ceiling) {
            return;
        }

        asort($living);

        $surplus = min($occupied - $ceiling, count($living));
        foreach (array_slice(array_keys($living), 0, $surplus) as $digest) {
            $this->drop($digest);
        }

        $this->logger->warn('Evicted cache entries that had not expired yet', [
            'evicted' => (string) $surplus,
            'capacity' => (string) $capacity,
        ]);
    }

    /**
     * Removes one entry from both tables.
     *
     * Index first, so a reader racing the deletion finds no entry rather than an
     * entry whose payload has already gone.
     *
     * @param  string  $digest  The key both tables share.
     *
     * @copyright 2026 Tachyon
     */
    private function drop(string $digest): void
    {
        $this->index->del($digest);
        $this->store->del($digest);
    }
}
