<?php

declare(strict_types=1);

namespace App\Context;

/**
 * A role as it reaches a use case: its identity plus the permission slugs it
 * grants.
 *
 * Part of {@see UserContext}; see that class for why the application layer owns
 * these types instead of reusing a domain model.
 */
final readonly class RoleContext
{
    /**
     * @param  list<string>  $permissions  Permission slugs granted by this role.
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $permissions,
    ) {
    }

    public function grants(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }
}
