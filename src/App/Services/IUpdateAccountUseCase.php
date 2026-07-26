<?php

declare(strict_types=1);

namespace App\Services;

use App\Commands\Account\UpdateAccountCommand;
use Domain\Models\IUser;
use Shared\Exceptions\Result;

interface IUpdateAccountUseCase
{
    /**
     * @return Result<IUser>
     */
    public function execute(UpdateAccountCommand $command): Result;
}
