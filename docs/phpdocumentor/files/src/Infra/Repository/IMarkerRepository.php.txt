<?php

/**
 * Marker Repository Contract.
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

use Domain\Models\IMarker;
use Shared\Exceptions\Result;

/**
 * Stores and reads {@see IMarker} flags.
 *
 * Unlike the metadata registries this is written on the request path — and
 * unlike every other repository here, it takes no part in the caller's unit of
 * work. The store is the cache process, which knows nothing of transactions, so
 * a `ROLLBACK` will not undo a write here. Nothing filed under a marker
 * participates in a business invariant, so there is nothing to undo.
 *
 * **Expiry is the reader's job, not a deleter's.** Every read filters on the
 * TTL, so an expired marker is indistinguishable from one that never existed and
 * no read ever has to delete. Reclaiming the memory is the sweeper's job, on a
 * timer, which is what keeps correctness independent of when it last ran.
 *
 * **The TTL belongs to the caller.** {@see set()} takes it as an argument
 * because a refresh-token marker has to outlive exactly the token it tracks, and
 * the next marker written will expire for some other reason.
 *
 * @see IMarker What is stored.
 * @see \Infra\Repository\Interno\CacheProcessMarkerRepository The implementation.
 * @see docs/adr/0011-cache-em-processo-openswoole.md Why markers live in the cache process.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IMarkerRepository
{
    /**
     * Writes the flag, creating the marker if it is not there yet, and sweeps
     * expired rows while it holds the lock.
     *
     * @param  IMarker  $marker  Carries the group, the digest and the flag.
     * @param  int  $ttlSeconds  How long the marker stays readable, counted from
     *                           now. Applied on every write, so consuming a
     *                           marker also decides how long the evidence of the
     *                           consumption survives.
     * @return Result<null> Void on success; a 404 failure when the group is not
     *                      registered, a 500 on a write error.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function set(IMarker $marker, int $ttlSeconds): Result;

    /**
     * Reads the flag of a live marker.
     *
     * @param  string  $group  Slug of a registered group.
     * @param  string  $key  The digest, as produced by
     *                       {@see \Domain\TableModules\IMarkerTM}.
     * @return Result<bool> The flag, or a **404** when there is no live marker —
     *                       expired and never-existed are the same answer,
     *                       deliberately. A 404 as well when the group is not
     *                       registered: a caller must not be able to tell the two
     *                       apart, or it learns whether a value was ever valid. A
     *                       500 on a read error, which is *not* the same thing —
     *                       {@see \App\Services\Interno\SetMarkerUseCase} raises a
     *                       marker on the first but never on the second.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function get(string $group, string $key): Result;
}
