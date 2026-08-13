<?php

/**
 * Marker.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Domain\Models\Internal;

use Domain\Models\IMarker;

/**
 * Concrete {@see IMarker}. Built only by
 * {@see \Domain\TableModules\IMarkerTM}, which is what turns the caller's plain
 * value into {@see $key} — this class never sees the original.
 *
 * @see IMarker What each property means.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class Marker implements IMarker
{
    /**
     * @param  string  $group  Slug of the group this flag belongs to.
     * @param  string  $key  Digest of the flagged value, never the value.
     * @param  bool  $flag  True while live, false once consumed.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public string $group,
        public string $key,
        public bool $flag,
    ) {
    }
}
