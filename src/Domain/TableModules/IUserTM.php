<?php

namespace Domain\TableModules;

use Domain\Models\IRole;
use Domain\Models\IUser;
use Shared\Exceptions\Result;

interface IUserTM
{
    /**
     * Creates a new user after validating input. Validation is internal to the
     * implementation: an invalid input surfaces as a failed {@see Result} (422),
     * never as a separate public step.
     *
     * @param  string  $name
     * @param  string  $email
     * @param  string  $password
     * @param  list<IRole>  $roles
     * @return Result<IUser>
     */
    public function create(
        string $name,
        string $email,
        string $password,
        array $roles,
    ): Result;

    /**
     * Produces the user with updated profile data (name/email), re-validated.
     *
     * @return Result<IUser> Failure (422) on invalid input.
     */
    public function update(IUser $user, string $name, string $email): Result;

    /**
     * Produces the user with a new (validated, hashed) password — self-service
     * change. Verifying the current password is the use case's job.
     *
     * @return Result<IUser> Failure (422) on a weak password.
     */
    public function changePassword(IUser $user, string $newPassword): Result;

    /**
     * Produces the user with a new (validated, hashed) password — admin reset,
     * no current-password check.
     *
     * @return Result<IUser> Failure (422) on a weak password.
     */
    public function resetPassword(IUser $user, string $newPassword): Result;
}