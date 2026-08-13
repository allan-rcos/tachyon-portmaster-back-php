<?php

/**
 * Role Context.
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
 * A role as it reaches a use case: its identity plus the permission slugs it
 * grants.
 *
 * Part of {@see UserContext}; see that class for why the application layer owns
 * these types instead of reusing a domain model.
 *
 * @see UserContext What carries these.
 * @see \App\Security\AuthorizesWithPermission What consults them.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class RoleContext
{
    /**
     * @param  string  $id  Base62 id of the role.
     * @param  string  $name  Display name, carried so a caller can be told which
     *                        role granted something.
     * @param  list<string>  $permissions  Permission slugs granted by this role.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $permissions,
    ) {
    }

    /**
     * Whether this role grants the slug.
     *
     * An exact, case-sensitive match — a permission is its slug and nothing
     * else, so there is no hierarchy or wildcard to expand.
     *
     * @param  string  $permission  Slug in `domain:action` form.
     * @return bool True when the role carries it.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function grants(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }
}
