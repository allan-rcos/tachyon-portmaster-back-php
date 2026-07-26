<?php

declare(strict_types=1);

namespace Domain\Models;

/**
 * A namespace for {@see IMarker} flags — **system metadata**, same family as
 * {@see IPermission} and {@see ITelemetryEvent}.
 *
 * It qualifies for the same reasons: groups are declared by the code that needs
 * them (a feature that wants a flag registers its group at boot), they are
 * queried at runtime, and the full set is rebuildable from the source alone.
 *
 * The group is what keeps markers from colliding. Two features hashing unrelated
 * values could produce the same digest, and without a group they would read each
 * other's flags; with one, a marker is only ever found by the pair
 * `(group, key)`.
 *
 * Like the other metadata, it carries only its identity — see {@see IPermission}
 * for why there is no label or description.
 */
interface IMarkerGroup
{
    /**
     * The stable identifier, a lower-kebab token (e.g. `refresh-token`). Named
     * after the *kind of flag*, never after the value being flagged.
     */
    public string $slug {
        get;
    }

    /**
     * Registry index, assigned on registration. This is the value stored on
     * every marker row, so it is a real foreign key in practice — but only
     * within the lifetime of the `MEMORY` table that holds both, which are
     * emptied together when MariaDB restarts. Zero means "built but not yet
     * registered".
     */
    public int $id {
        get;
    }

    public function withId(int $id): self;
}
