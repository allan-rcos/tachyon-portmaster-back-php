<?php

/**
 * Set Marker Command.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App\Commands\Marker;

/**
 * Sets the flag on the marker for a value.
 *
 * No {@see \App\Context\UserContext}: a marker is infrastructure for whoever
 * issued the value, not an action a user performs — the caller has already
 * decided it may do this, and there is no permission that would mean anything
 * here. The refresh flow, for instance, sets markers for a caller who by
 * definition is not authenticated yet.
 *
 * @see \App\Services\ISetMarkerUseCase What consumes it.
 * @see RegisterMarkerGroupCommand Declares the group this files under.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class SetMarkerCommand
{
    /**
     * @param  string  $group  Slug of a registered marker group.
     * @param  string  $value  The plain value being flagged. Hashed by the domain
     *                         and never stored — see {@see \Domain\TableModules\IMarkerTM}.
     * @param  bool  $flag  `true` to mark live, `false` to consume.
     * @param  int  $ttlSeconds  How long the marker stays readable from now.
     *                           Applied on every write, so consuming a marker
     *                           also decides how long the evidence survives.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public string $group,
        public string $value,
        public bool $flag,
        public int $ttlSeconds,
    ) {
    }
}
