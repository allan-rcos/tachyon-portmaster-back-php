<?php

namespace Infra\Repository;

use Domain\Models\IRole;
use Shared\Exceptions\Result;

interface IRoleRepository
{
    /**
     * Finds a role by its ID.
     * @param  string  $id  The ID of the role to find
     * @return Result<IRole> The found role or an error if not found
     */
    public function findById(string $id): Result;

    /**
     * Loads every role assigned to a user (through the `user_roles` pivot).
     * @param  string  $userId  The ID of the user whose roles to load
     * @return Result<IRole[]> The user's roles (possibly empty), or an infrastructure error
     */
    public function findByUserId(string $userId): Result;

    /**
     * Insert a role to the repository.
     * @param  IRole  $role  The role to save
     * @return Result<null> Infrastructure error or void if successful
     */
    public function insert(IRole $role): Result;

    /**
     * Updates a role in the repository.
     * @param  IRole  $role  The role to update
     * @return Result<null> Infrastructure error or void if successful
     */
    public function update(IRole $role): Result;

    /**
     * Deletes a role by its ID.
     * @param  string  $id  The ID of the role to delete
     * @return Result<null> Infrastructure error or void if successful
     */
    public function delete(string $id): Result;
}