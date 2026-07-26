<?php

declare(strict_types=1);

namespace Domain\Models\Internal;

use Domain\Models\IMarker;

/**
 * Concrete {@see IMarker}. Built only by
 * {@see \Domain\TableModules\IMarkerTM}, which is what turns the caller's plain
 * value into {@see $key} — this class never sees the original.
 */
final readonly class Marker implements IMarker
{
    public function __construct(
        public string $group,
        public string $key,
        public bool $flag,
    ) {
    }
}
