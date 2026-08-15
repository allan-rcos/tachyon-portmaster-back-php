<?php

/**
 * Role Contract.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

namespace Domain\Models;

/**
 * A named bundle of permissions, granted to users.
 *
 * @see \Domain\TableModules\IRoleTM Builds these and validates the slugs.
 * @see IUser Holds roles; never permissions directly.
 * @see IPermission What the slugs name.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IRole
{
    /**
     * @var string Application-generated Snowflake, Base62-encoded at the edge.
     */
    public string $id {
        get;
    }

    /**
     * @var string Display name (e.g. `Administrator`).
     */
    public string $name {
        get;
    }

    /**
     * Permission slugs this role grants.
     *
     * Slugs, not {@see IPermission} objects: a role is persisted as JSON and
     * outlives any particular registry, whose numeric ids are handed out afresh
     * every time the catalogue is rebuilt at `WorkerStart`. The slug is the only
     * stable handle.
     *
     * Updating a role **replaces** this list rather than merging into it, so an
     * omitted slug is a revoked permission.
     *
     * @var list<string> Slugs in `domain:action` form.
     */
    public array $permissions {
        get;
    }
}
