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
 * Unlike the metadata registries this is written on the request path, so it
 * takes part in the caller's unit of work like any other repository — with one
 * caveat: the table is `ENGINE=MEMORY` and therefore non-transactional, so a
 * `ROLLBACK` will not undo a write here.
 *
 * **Expiry is the reader's job, not a deleter's.** Every read filters on the
 * TTL, so an expired marker is indistinguishable from one that never existed and
 * no read ever has to delete. That matters because MEMORY locks at table
 * granularity: sweeping on read would serialise every request behind a write
 * lock. The sweep therefore happens on {@see set()}, where a lock is already
 * being taken.
 *
 * @see IMarker What is stored.
 * @see \Infra\Repository\Interno\SqlMarkerRepository The implementation.
 * @see docs/adr/0003-engine-memory-for-runtime-tables.md What the MEMORY engine costs here.
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
     * @return Result<bool|null> The flag, or null when there is no live marker —
     *                           expired and never-existed are the same answer,
     *                           deliberately. A 404 failure when the group is
     *                           not registered, which is a different thing from
     *                           the group being empty; a 500 on a read error.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function get(string $group, string $key): Result;
}
