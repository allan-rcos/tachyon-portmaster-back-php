<?php

namespace Domain\Ports\TableModules;

use Domain\Enums\Permissions;
use Domain\Models\IRole;
use Shared\Exceptions\Result;

interface IRoleTM
{
    /**
     * Creates a new role.
     * @param  string  $name  The name of the role
     * @param  Permissions[]  $permissions  Array of permission IDs to assign to the role
     * @return Result<IRole> The created role with its assigned permissions
     */
    public function create(
        string $name,
        array $permissions,
    ): Result;
}