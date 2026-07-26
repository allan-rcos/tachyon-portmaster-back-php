<?php

declare(strict_types=1);

namespace App\Services;

use App\Commands\User\UpdateUserCommand;
use Domain\Models\IUser;
use Shared\Exceptions\Result;

interface IUpdateUserUseCase
{
    /**
     * @return Result<IUser> The updated user, or 404 when not found.
     */
    public function execute(UpdateUserCommand $command): Result;
}
