<?php

namespace Domain\TableModules;

use Domain\Models\IRole;
use Shared\Exceptions\Result;

interface IRoleTM
{
    /**
     * Creates a new role.
     * @param  string  $name  The name of the role
     * @param  list<string>  $permissions  Permission slugs to assign to the role
     * @return Result<IRole> The created role with its assigned permissions
     */
    public function create(
        string $name,
        array $permissions,
    ): Result;

    /**
     * Produces the role with its permission set replaced by the given one.
     * @param  IRole  $role  The role being modified
     * @param  list<string>  $permissions  Permission slugs to set (full replacement)
     * @return Result<IRole> The updated role
     */
    public function updatePermissions(
        IRole $role,
        array $permissions,
    ): Result;
}