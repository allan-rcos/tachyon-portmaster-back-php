<?php

declare(strict_types=1);

namespace App\Services;

use App\Commands\Role\CreateRoleCommand;
use Domain\Models\IRole;
use Shared\Exceptions\Result;

interface ICreateRoleUseCase
{
    /**
     * @return Result<IRole>
     */
    public function execute(CreateRoleCommand $command): Result;
}
