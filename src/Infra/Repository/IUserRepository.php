<?php

namespace Infra\Repository;

use Domain\Models\IUser;
use Shared\Exceptions\Result;

interface IUserRepository
{
    /**
     * Whether the system has any user at all.
     *
     * Exists for the bootstrap endpoint, which must open exactly once: a
     * deployment with no users cannot create one through `/users`, because that
     * needs a permission that needs a role that needs a user to grant it.
     *
     * @return Result<bool> Infrastructure error, or the answer.
     */
    public function hasAny(): Result;

    /**
     * Finds a user by their ID.
     * @param  string  $id
     * @return Result<IUser> The found user or an error if not found
     */
    public function findById(string $id): Result;

    /**
     * Finds a user by their email.
     * @param  string  $email
     * @return Result<IUser> The found user or an error if not found
     */
    public function findByEmail(string $email): Result;

    /**
     * Inset a new user.
     * @param  IUser  $user
     * @return Result<null> Infrastructure error or void if successful
     */
    public function insert(IUser $user): Result;

    /**
     * Updates an existing user.
     * @param  IUser  $user
     * @return Result<null> Infrastructure error or void if successful
     */
    public function update(IUser $user): Result;

    /**
     * Deletes a user by their ID.
     * @param  string  $id
     * @return Result<null> Infrastructure error or void if successful
     */
    public function delete(string $id): Result;

    /**
     * Replaces the user's role assignments in the `user_roles` pivot with the
     * given set (full sync — removes any not listed, adds any missing).
     * @param  string  $userId
     * @param  list<string>  $roleIds
     * @return Result<null> Infrastructure error or void if successful
     */
    public function syncRoles(string $userId, array $roleIds): Result;
}