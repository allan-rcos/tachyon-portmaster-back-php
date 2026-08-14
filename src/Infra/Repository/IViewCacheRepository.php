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
 * implementation's business — an `expires_at` column here, a `SETEX` elsewhere.
 *
 * Only the listings and the metrics panel go through here. Reads by id do not,
 * unlike the Rust implementation this mirrors.
 *
 * @see ViewCacheGroup What a write drops.
 * @see \Infra\Query\IDQL::cacheKey() Where the key comes from.
 * @see \Infra\Repository\Interno\SqlViewCacheRepository The implementation.
 * @see docs/adr/0010-read-cache-in-a-memory-table.md Why the cache is shaped this way, and why reads by id are out.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IViewCacheRepository
{
    /**
     * The View stored under this key, if there is one.
     *
     * An entry that does not deserialize is reported as **absence**, not as a
     * failure: it was written in an earlier format, and recomputing it in
     * silence is what keeps a deploy from becoming an incident.
     *
     * @param  ViewCacheGroup  $group  The slice to look in.
     * @param  string  $key  From the DQL that is about to be run.
     * @return Result<mixed> The stored View; {@see Result::void()} on a miss,
     *                       which an expired, an unreadable and an absent entry
     *                       all are. A failure only when the store itself broke,
     *                       and even then the caller is expected to carry on to
     *                       the database.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function get(ViewCacheGroup $group, string $key): Result;

    /**
     * Stores the View under this key.
     *
     * Storing is best-effort by contract. A View the implementation cannot hold
     * — too large for its backing store, or not serializable — is dropped
     * silently and reported as success: the caller already has the correct
     * answer, and the only cost is that the next request recomputes it. Failing
     * here would turn a full cache into a failed read.
     *
     * @param  ViewCacheGroup  $group  The slice this belongs to.
     * @param  string  $key  The same key {@see get()} will be asked for.
     * @param  mixed  $view  The hydrated View, as the DQL produced it.
     * @return Result<null> Void on success, including when the entry was
     *                      deliberately not stored. A failure only when the
     *                      store broke in a way worth recording.
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
