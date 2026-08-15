<?php

/**
 * View Cache Repository Contract.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra\Repository;

use Shared\Exceptions\Result;

/**
 * Holds what a read query returned.
 *
 * One repository for every View rather than one per resource: what it stores is
 * always the same thing — what a query answered. It is the read-side twin of the
 * write repositories under this namespace, and the only one of them that stores
 * bytes instead of rows.
 *
 * The **key** comes from {@see \Infra\Query\IDQL::cacheKey()}, and the DQL
 * writes it because the DQL is what knows what the query is. The **group** is
 * chosen by the caller, and is the slice one write drops.
 *
 * **This port is the seam**, so everything above it names a group and a key and
 * gets a View back without knowing where entries live. {@see put()} therefore
 * takes no TTL: the lifetime is
 * {@see \Infra\Config\CacheLimits::TTL_SECONDS}, and applying it is the
 * implementation's business — an expiry stamped on the entry here, a `SETEX`
 * elsewhere.
 *
 * Only the listings and the metrics panel go through here. Reads by id do not,
 * unlike the Rust implementation this mirrors.
 *
 * @see ViewCacheGroup What a write drops.
 * @see \Infra\Query\IDQL::cacheKey() Where the key comes from.
 * @see \Infra\Repository\Interno\CacheProcessViewCacheRepository The implementation.
 * @see docs/adr/0010-read-cache-in-a-memory-table.md Why the cache is shaped this way, and why reads by id are out.
 * @see docs/adr/0011-cache-em-processo-openswoole.md Where the entries live now.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IViewCacheRepository
{
    /**
     * The View stored under this key, if there is one.
     *
     * @param  ViewCacheGroup  $group  The slice to look in.
     * @param  string  $key  From the DQL that is about to be run.
     * @return Result<mixed> The stored View. A **404** when nothing live is filed
     *                       under the key, and some other failure when the store
     *                       itself broke. The caller carries on to the database in
     *                       either case — but it is the caller that decides that,
     *                       and a port answering an empty success would have made
     *                       the decision for it.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function get(ViewCacheGroup $group, string $key): Result;

    /**
     * Stores the View under this key.
     *
     * A View the implementation cannot hold — too large for its backing store,
     * or not serializable — is a reported failure, not a silent drop. Callers
     * ignore it and should: they already hold the correct answer, and the only
     * cost is that the next request recomputes it. Ignoring a failure is a
     * decision; never being told is not.
     *
     * @param  ViewCacheGroup  $group  The slice this belongs to.
     * @param  string  $key  The same key {@see get()} will be asked for.
     * @param  mixed  $view  The hydrated View, as the DQL produced it.
     * @return Result<null> Void once stored; a failure when it could not be.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function put(ViewCacheGroup $group, string $key, mixed $view): Result;

    /**
     * Discards the whole group.
     *
     * Called **after** the commit it follows, never before: a concurrent read
     * that ran in between would repopulate the cache with the state it can still
     * see, which is the state being replaced.
     *
     * No prefix scan — the group is already the slice, and comparing prefixes
     * would match `container` against a hypothetical `container_summary`.
     *
     * @param  ViewCacheGroup  $group  Everything filed under it goes.
     * @return Result<null> Void on success; a failure when the statement threw.
     *                      A caller that has already committed is expected to
     *                      ignore it — the write happened, and the entries it
     *                      outdated expire on their own.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function invalidate(ViewCacheGroup $group): Result;
}
