<?php

declare(strict_types=1);

namespace App\Services;

use App\Commands\User\UpdateUserRolesCommand;
use Domain\Models\IUser;
use Shared\Exceptions\Result;

interface IUpdateUserRolesUseCase
{
    /**
     * @return Result<IUser> The user, or 404 when not found.
     */
    public function execute(UpdateUserRolesCommand $command): Result;
}
