<?php

declare(strict_types=1);

namespace App\Commands\Role;

use App\Context\UserContext;


final readonly class CreateRoleCommand
{
    /**
     * @param  Permission[]  $permissions
     */
    public function __construct(
        public UserContext $context,
        public string $name,
        /** @var list<string> Permission slugs to be created with the role. */
        public array $permissions,
    ) {
    }
}
