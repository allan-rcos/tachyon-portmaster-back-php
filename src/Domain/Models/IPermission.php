<?php

declare(strict_types=1);

namespace Domain\Models;

/**
 * An authorization permission — the first citizen of the project's **system
 * metadata** family.
 *
 * Metadata is anything that (a) grows as the code grows, (b) needs to be queried
 * at runtime, and (c) can be rebuilt identically from scratch on every restart.
 * A permission qualifies on all three: it belongs to a single use case, a new
 * use case means a new permission, and the full set is knowable from the code
 * alone. That is why it is **not** an enum — an enum would force the domain to
 * enumerate every permission the application layer will ever invent, inverting
 * the dependency.
 *
 * So the domain declares only the shape. The application layer owns the values
 * (each use case registers its own at boot) and the infrastructure layer owns
 * the storage ({@see \Infra\Repository\IPermissionRepository}, a `ENGINE=MEMORY`
 * table rebuilt on every boot).
 *
 * **It carries nothing but its identity.** A label and a description used to
 * live here and nothing ever read them: authorization compares slugs, roles
 * persist slugs, and the wire contract exposes slugs. Since the storage is RAM —
 * where MariaDB pads every `VARCHAR` to its full width — two unread text columns
 * cost several times the rows they describe. If a permission-management screen
 * ever needs prose, it belongs wherever that screen's copy lives, not in the
 * table consulted on every authorization check.
 *
 * {@see \Domain\Models\ITelemetryEvent} follows the same pattern. Enums that do
 * *not* qualify — a closed, business-defined set like `ContainerStatus` or
 * `RiskClass` — stay enums.
 */
interface IPermission
{
    /**
     * The stable identifier, `domain:action` (e.g. `product:create`). This is the
     * value that travels on the wire and is persisted against a role.
     */
    public string $slug {
        get;
    }

    /**
     * The registry's internal index, assigned on registration.
     *
     * A handle for the lookup table: never serialized, never persisted against a
     * role, never exposed. It is stable only while the registry table lives — a
     * `MEMORY` table is emptied when MariaDB restarts, and the ids are then
     * handed out again in whatever order the workers happen to register — so
     * nothing may depend on a particular value. Zero means "built but not yet
     * registered".
     */
    public int $id {
        get;
    }

    /**
     * The same permission carrying the registry index — used by the repository at
     * registration time, since the id is not knowable when the domain builds it.
     */
    public function withId(int $id): self;
}
