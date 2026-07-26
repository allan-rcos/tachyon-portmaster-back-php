<?php

declare(strict_types=1);

namespace Infra\Query\Container;

final readonly class TelemetryLogView
{
    public function __construct(
        public string $id,
        /** The telemetry event slug (see {@see \Domain\Models\ITelemetryEvent}). */
        public string $event,
        public ?string $description,
        public ?string $timestamp,
    ) {
    }
}
