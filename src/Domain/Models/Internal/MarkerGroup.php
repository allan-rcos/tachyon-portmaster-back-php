<?php

declare(strict_types=1);

namespace Domain\Models\Internal;

use Domain\Models\IMarkerGroup;

/**
 * Concrete {@see IMarkerGroup}. Built only by
 * {@see \Domain\TableModules\IMarkerGroupTM}, which validates it first.
 */
final readonly class MarkerGroup implements IMarkerGroup
{
    public function __construct(
        public string $slug,
        public int $id = 0,
    ) {
    }

    public function withId(int $id): IMarkerGroup
    {
        return new self($this->slug, $id);
    }
}
