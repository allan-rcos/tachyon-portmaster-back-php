<?php

namespace Domain\TableModules;

use Domain\Models\IUser;
use Shared\Exceptions\Result;

interface IAuthTM
{
    /**
     * Validates a plaintext password against the user's stored hash.
     *
     * @param  IUser  $user  The user whose credentials are being checked
     * @param  string  $password  The plaintext password to verify
     * @return Result<null> Void when the password matches; failure (401) otherwise
     */
    public function login(IUser $user, string $password): Result;
}
