<?php

declare(strict_types=1);

namespace App\Services;

use App\Commands\User\CreateUserCommand;
use Domain\Models\IUser;
use Shared\Exceptions\Result;

interface ICreateUserUseCase
{
    /**
     * @return Result<IUser> The created user, or 409 when the e-mail is taken.
     */
    public function execute(CreateUserCommand $command): Result;
}
