<?php

declare(strict_types=1);

namespace Infra\Repository;

use Domain\Models\ITelemetryEvent;
use Ds\Seq;
use Shared\Exceptions\Result;

/**
 * The telemetry-event registry — the {@see IPermissionRepository} pattern applied
 * to the other metadata family.
 *
 * Same reasoning: the set of event types is knowable from the code, so it is
 * rebuilt in memory at worker start rather than persisted. The telemetry log
 * rows persist the event **slug**, not the registry index.
 */
interface ITelemetryEventRepository
{
    /**
     * Registers an event type and returns it with its registry index. Idempotent
     * by slug.
     *
     * @return Result<ITelemetryEvent> Failure 500 when the registry is full.
     */
    public function add(ITelemetryEvent $event): Result;

    public function getBySlug(string $slug): ?ITelemetryEvent;

    public function getById(int $id): ?ITelemetryEvent;

    /**
     * @return Seq<ITelemetryEvent>
     */
    public function all(): Seq;

    public function has(string $slug): bool;
}
