<?php

declare(strict_types=1);

namespace App\Services;

use App\Commands\Role\UpdateRolePermissionsCommand;
use Domain\Models\IRole;
use Shared\Exceptions\Result;

interface IUpdateRolePermissionsUseCase
{
    /**
     * @return Result<IRole> The updated role, or 404 when it does not exist.
     */
    public function execute(UpdateRolePermissionsCommand $command): Result;
}
