<?php

/**
 * User Context.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App\Context;

/**
 * Who is calling, as the application layer sees them.
 *
 * Every Command and Query carries one, and each use case consults it to decide
 * whether the caller may proceed. That is the whole point: **authorization is an
 * application concern**, not a presentation one. The API layer no longer decides
 * anything — it establishes this context from the session and maps the resulting
 * failure to a status code.
 *
 * It is a context type owned by `App` rather than a domain model because it is
 * request-scoped, not a business entity: it exists for the duration of one call
 * and models the caller, not the system. (A multi-tenant application would add a
 * sibling `TenantContext` here; this one is single-tenant, so there is none.)
 *
 * Permissions are checked through the roles, never stored flattened, so the
 * "which role granted this?" answer stays available.
 *
 * @see RoleContext The roles it carries.
 * @see \App\Security\AuthorizesWithPermission What consults it.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class UserContext
{
    /**
     * @param  string  $id  Base62 id of the caller.
     * @param  string  $name  Their display name.
     * @param  string  $email  Their address.
     * @param  list<RoleContext>  $roles  Every role they hold; empty for a
     *                                    caller who holds none, who is then
     *                                    refused by every guarded use case.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public array $roles,
    ) {
    }

    /**
     * Whether any of the caller's roles grants the permission slug.
     *
     * The single question authorization asks. Stops at the first role that
     * grants it, so which one did is not reported — {@see permissions()} is
     * there for callers that need the whole picture.
     *
     * @param  string  $permission  Slug in `domain:action` form.
     * @return bool True when at least one role grants it.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function hasPermission(string $permission): bool
    {
        foreach ($this->roles as $role) {
            if ($role->grants($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The de-duplicated union of every permission slug the caller holds.
     *
     * Flattened here rather than stored flattened, so the roles remain the
     * source of truth and a slug granted by two roles still appears once. This
     * is what a caller's own profile response reports; authorization itself uses
     * {@see hasPermission()}.
     *
     * @return list<string> In the order first encountered across the roles.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function permissions(): array
    {
        $permissions = [];

        foreach ($this->roles as $role) {
            foreach ($role->permissions as $permission) {
                if (!in_array($permission, $permissions, true)) {
                    $permissions[] = $permission;
                }
            }
        }

        return $permissions;
    }
}
