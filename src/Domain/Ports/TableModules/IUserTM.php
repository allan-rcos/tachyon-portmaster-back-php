<?php

namespace Domain\Ports\TableModules;

use Domain\Models\IUser;
use Ds\Map;
use Shared\Exceptions\Result;

interface IUserTM
{
    /**
     * Creates a new user after validating input.
     * @param  string  $name
     * @param  string  $email
     * @param  string  $password
     * @param  array  $roles
     * @return Result<IUser>
     */
    public function create(
        string $name,
        string $email,
        string $password,
        array $roles,
    ): Result;

    /**
     * Validates user input.
     * @param  string  $name
     * @param  string  $email
     * @param  string  $password
     * @return Map
     */
    public function validate(
        string $name,
        string $email,
        string $password,
    ): Map;
}