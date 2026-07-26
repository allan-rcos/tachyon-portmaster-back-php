<?php

declare(strict_types=1);

namespace App\Commands\Role;

use App\Context\UserContext;


final readonly class UpdateRolePermissionsCommand
{
    /**
     * @param  Permission[]  $permissions
     */
    public function __construct(
        public UserContext $context,
        public string $id,
        /** @var list<string> Permission slugs to be set on the role. */
        public array $permissions,
    ) {
    }
}
