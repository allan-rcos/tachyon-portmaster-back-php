<?php

declare(strict_types=1);

namespace Domain\Models\Internal;

use Domain\Models\ITelemetryEvent;

/**
 * Concrete {@see ITelemetryEvent}, built only by
 * {@see \Domain\TableModules\ITelemetryEventTM}.
 */
final readonly class TelemetryEvent implements ITelemetryEvent
{
    public function __construct(
        public string $slug,
        public int $id = 0,
    ) {
    }

    public function withId(int $id): ITelemetryEvent
    {
        return new self($this->slug, $id);
    }
}
