<?php

/**
 * Cache Process View Cache Repository.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra\Repository\Interno;

use Infra\Cache\CacheProcessEntryConfig;
use Infra\Cache\ICacheProcessDatabase;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Shared\Exceptions\Result;

/**
 * {@see IViewCacheRepository} over the shared cache.
 *
 * Thin on purpose. Everything that used to make this class long — serialising
 * the view, deciding what is too large to hold, filtering what has expired —
 * belongs to {@see ICacheProcessDatabase} and is done once there for all four
 * repositories. Its failures are passed straight through, because the caller
 * that knows a miss just means "read the database instead" is the use case, not
 * this. What is left is the mapping from this port's vocabulary to that one's:
 *
 *  - a **group and a key** become a suffix, `"{$group->value}:{$key}"`;
 *  - **invalidating a group** becomes a prefix clean on `"{$group->value}:"`.
 *
 * The trailing colon in that prefix is load-bearing: without it, dropping
 * `container` would also drop a hypothetical `container_summary`. It is the same
 * trap the MEMORY-table implementation avoided by refusing to compare prefixes
 * at all, and the reason it could refuse was that the group was a column of its
 * own.
 *
 * The lifetime is {@see \Infra\Config\CacheLimits::VIEW_TTL_SECONDS}, applied by
 * the database rather than named here — which is what
 * {@see IViewCacheRepository::put()} means by taking no TTL.
 *
 * @see IViewCacheRepository The contract this implements.
 * @uses ICacheProcessDatabase The `view` slice, carrying the read TTL.
 * @see ICacheProcessDatabase Where the work actually happens.
 * @see ViewCacheGroup What a write drops.
 * @see docs/adr/0011-cache-em-processo-openswoole.md Why the cache is shaped this way.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class CacheProcessViewCacheRepository implements IViewCacheRepository
{
    /**
     * @param  ICacheProcessDatabase  $database  The `view` slice of the shared
     *                                           cache, carrying the read TTL.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private ICacheProcessDatabase $database,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @param  ViewCacheGroup  $group  The slice to look in.
     * @param  string  $key  From the DQL about to be run.
     * @return Result<mixed> Whatever the slice answered, passed through
     *                        untouched: a 404 for a miss, a failure for a store
     *                        that broke.
     *
     * @copyright 2026 Tachyon
     */
    public function get(ViewCacheGroup $group, string $key): Result
    {
        return $this->database->get(new CacheProcessEntryConfig($this->suffix($group, $key)));
    }

    /**
     * {@inheritDoc}
     *
     * @param  ViewCacheGroup  $group  The slice this belongs to.
     * @param  string  $key  The key {@see get()} will be asked for.
     * @param  mixed  $view  The hydrated view, as the DQL produced it.
     * @return Result<null> Void once stored; a failure when it could not be.
     *
     * @copyright 2026 Tachyon
     */
    public function put(ViewCacheGroup $group, string $key, mixed $view): Result
    {
        return $this->database->put($view, new CacheProcessEntryConfig($this->suffix($group, $key)));
    }

    /**
     * {@inheritDoc}
     *
     * @param  ViewCacheGroup  $group  Everything filed under it goes.
     * @return Result<null> Void on success. A caller that has already committed
     *                      is expected to ignore a failure — the write happened,
     *                      and the entries it outdated expire on their own.
     *
     * @copyright 2026 Tachyon
     */
    public function invalidate(ViewCacheGroup $group): Result
    {
        return $this->database->clean(new CacheProcessEntryConfig($group->value.':'));
    }

    /**
     * Where one cached view sits inside the `view` slice.
     *
     * @param  ViewCacheGroup  $group  The slice.
     * @param  string  $key  The query's identity, from the DQL.
     * @return string The suffix identifying the entry.
     *
     * @copyright 2026 Tachyon
     */
    private function suffix(ViewCacheGroup $group, string $key): string
    {
        return $group->value.':'.$key;
    }
}
