<?php

namespace Domain\Ports\Repository;

use Domain\Models\IUser;
use Shared\Exceptions\Result;

interface IUserRepository
{
    /**
     * Finds a user by their ID.
     * @param  int  $id
     * @return Result<IUser> The found user or an error if not found
     */
    public function findById(int $id): Result;

    /**
     * Finds a user by their email.
     * @param  string  $email
     * @return Result<IUser> The found user or an error if not found
     */
    public function findByEmail(string $email): Result;

    /**
     * Inset a new user.
     * @param  IUser  $user
     * @return Result<void> Infrastructure error or void if successful
     */
    public function insert(IUser $user): Result;

    /**
     * Updates an existing user.
     * @param  IUser  $user
     * @return Result<void> Infrastructure error or void if successful
     */
    public function update(IUser $user): Result;

    /**
     * Deletes a user by their ID.
     * @param  int  $id
     * @return Result<void> Infrastructure error or void if successful
     */
    public function delete(int $id): Result;
}