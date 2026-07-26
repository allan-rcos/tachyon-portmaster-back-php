<?php

declare(strict_types=1);

namespace App\Services;

use App\Commands\Account\ChangePasswordCommand;
use Shared\Exceptions\Result;

interface IChangePasswordUseCase
{
    /**
     * @return Result<null> Void on success; 401 when the current password is
     *                      wrong, 422 when the new one is too weak.
     */
    public function execute(ChangePasswordCommand $command): Result;
}
