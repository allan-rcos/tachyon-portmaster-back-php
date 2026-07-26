<?php

declare(strict_types=1);

namespace Domain\TableModules;

use Domain\Models\ITelemetryEvent;
use Shared\Exceptions\Result;

interface ITelemetryEventTM
{
    /**
     * Builds a telemetry event type after validating it.
     *
     * The returned event has `id = 0`: the registry index is assigned later, by
     * {@see \Infra\Repository\ITelemetryEventRepository::add()}.
     *
     * @param  string  $slug  Lower-kebab single token (e.g. `load`, `seal`).
     * @return Result<ITelemetryEvent> Failure 422 when the slug is malformed.
     */
    public function create(string $slug): Result;
}
