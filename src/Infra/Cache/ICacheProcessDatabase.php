<?php

/**
 * Cache Process Database Contract.
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

use Shared\Exceptions\Result;

/**
 * One slice of the shared cache, addressed by suffix.
 *
 * Three methods and nothing else. Everything that varies between two callers is
 * in the two config objects — {@see CacheProcessDatabaseConfig} for what the
 * slice *is*, {@see CacheProcessEntryConfig} for what one operation wants — so
 * the surface does not grow when a new kind of entry shows up.
 *
 * **Values, not bytes.** Serialization is this port's business: a caller hands
 * over the object it has and gets the object back. That keeps `igbinary` in one
 * place instead of in four repositories, and it is what lets a different
 * implementation of this interface choose a different encoding without anything
 * above it noticing.
 *
 * **Every failure is reported.** A miss is a 404, a key or a value this store
 * cannot address is a 422, and a store that broke is a 500 — none of them is
 * flattened into an empty success. That a miss merely means "ask the database
 * instead" is true, but it is the *use case* that knows it: the entry it wanted
 * genuinely was not there, and a port that answered success would be lying about
 * it. {@see Result::void()} appears here only where there is nothing to return
 * and nothing went wrong.
 *
 * @see \Infra\Cache\Interno\TableCacheProcessDatabase The implementation.
 * @see IOpenSwooleCacheProcessAdapter What hands these out.
 * @see \Infra\Repository\IViewCacheRepository One of the ports built on top.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface ICacheProcessDatabase
{
    /**
     * The value stored under this suffix, if there is one.
     *
     * {@see CacheProcessEntryConfig::$ttlSeconds} is ignored here — a read has
     * no lifetime to set.
     *
     * @param  CacheProcessEntryConfig|null  $entry  Which entry; `null` addresses
     *                                               the database's bare key.
     * @return Result<mixed> The stored value. A **404** when nothing live is
     *                       filed under the key, which an absent and an expired
     *                       entry both are; a 422 when the key could never have
     *                       been stored; a 500 when the store holds bytes it
     *                       cannot read back. A caller that wants to recompute
     *                       ignores all three alike — but it is that caller's
     *                       decision, not this port's.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function get(?CacheProcessEntryConfig $entry = null): Result;

    /**
     * Stores the value under this suffix, replacing whatever was there.
     *
     * A value the implementation cannot hold — wider than its backing store, or
     * not serializable — is a **reported** failure rather than a silent drop.
     * Storing is still best-effort in the sense that a caller may ignore the
     * result and almost always should: it already holds the correct answer, and
     * the only cost is that the next request recomputes it. Ignoring a failure
     * is a decision; never being told is not.
     *
     * This is the one method that reads
     * {@see CacheProcessEntryConfig::$ttlSeconds}, and the reason that field
     * exists.
     *
     * @param  mixed  $value  The object to keep, as the caller holds it.
     * @param  CacheProcessEntryConfig|null  $entry  Which entry, and for how
     *                                               long; `null` uses the
     *                                               database's key and TTL.
     * @return Result<null> Void once stored. A 422 when the key or the value is
     *                      wider than the store can hold, a 500 when the write
     *                      itself failed.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function put(mixed $value, ?CacheProcessEntryConfig $entry = null): Result;

    /**
     * Discards every entry whose key starts with this suffix.
     *
     * A **prefix**, not a key: that is the difference between this and the other
     * two methods, and it is what makes invalidating a whole group one call.
     * `null` drops the database's entire slice.
     *
     * Called **after** the commit it follows, never before: a concurrent read
     * that ran in between would repopulate the cache with the state it can still
     * see, which is the state being replaced.
     *
     * {@see CacheProcessEntryConfig::$ttlSeconds} is ignored here.
     *
     * @param  CacheProcessEntryConfig|null  $entry  The prefix to drop under;
     *                                               `null` drops the whole
     *                                               database.
     * @return Result<null> Void once dropped; a failure when the store broke. A
     *                      caller that has already committed is expected to
     *                      ignore it — the write happened, and the entries it
     *                      outdated expire anyway.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function clean(?CacheProcessEntryConfig $entry = null): Result;
}
