<?php

namespace Domain\Ports\Repository;

use Domain\Models\IRole;
use Shared\Exceptions\Result;

interface IRoleRepository
{
    /**
     * Finds a role by its ID.
     * @param  int  $id  The ID of the role to find
     * @return Result<IRole> The found role or an error if not found
     */
    public function findById(int $id): Result;

    /**
     * Insert a role to the repository.
     * @param  IRole  $role  The role to save
     * @return Result<void> Infrastructure error or void if successful
     */
    public function insert(IRole $role): Result;

    /**
     * Updates a role in the repository.
     * @param  IRole  $role  The role to update
     * @return Result<void> Infrastructure error or void if successful
     */
    public function update(IRole $role): Result;

    /**
     * Deletes a role by its ID.
     * @param  int  $id  The ID of the role to delete
     * @return Result<void> Infrastructure error or void if successful
     */
    public function delete(int $id): Result;
}