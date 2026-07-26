<?php

declare(strict_types=1);

namespace Domain\Models;

/**
 * A container telemetry event type — **system metadata**, same family as
 * {@see IPermission}.
 *
 * Telemetry is the audit trail, and an audit trail grows with the system: every
 * new operation worth recording adds an event type. It is queryable (the logs
 * screen lists and filters by it) and fully rebuildable from code on restart, so
 * it satisfies the metadata test and stops being an enum.
 *
 * Like a permission, it carries only its identity — see {@see IPermission} for
 * why the label and description are gone.
 *
 * Contrast with {@see \Domain\Enums\ContainerStatus} and
 * {@see \Domain\Enums\RiskClass}: those are closed sets fixed by the business
 * (a container is Empty/Loading/Sealed/InTransit, full stop), so they remain
 * enums.
 */
interface ITelemetryEvent
{
    /** The stable identifier persisted in the telemetry log (e.g. `load`). */
    public string $slug {
        get;
    }

    /**
     * Registry index, assigned on registration. A lookup handle only, never
     * persisted — and, like {@see IPermission::$id}, only stable while the
     * `MEMORY` table holding the registry lives.
     */
    public int $id {
        get;
    }

    public function withId(int $id): self;
}
