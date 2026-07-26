<?php

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
 */
final readonly class UserContext
{
    /**
     * @param  list<RoleContext>  $roles
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
     * @return list<string>
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
