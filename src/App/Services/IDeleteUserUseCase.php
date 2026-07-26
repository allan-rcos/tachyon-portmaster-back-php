<?php

declare(strict_types=1);

namespace App\Services;

use App\Commands\User\DeleteUserCommand;
use Shared\Exceptions\Result;

interface IDeleteUserUseCase
{
    /**
     * @return Result<null> Void on success, 404 when the user does not exist.
     */
    public function execute(DeleteUserCommand $command): Result;
}
