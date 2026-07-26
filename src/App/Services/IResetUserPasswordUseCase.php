<?php

declare(strict_types=1);

namespace App\Services;

use App\Commands\User\ResetUserPasswordCommand;
use Shared\Exceptions\Result;

interface IResetUserPasswordUseCase
{
    /**
     * @return Result<null> Void on success, 404 when the user does not exist.
     */
    public function execute(ResetUserPasswordCommand $command): Result;
}
