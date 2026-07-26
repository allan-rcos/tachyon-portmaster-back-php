<?php

declare(strict_types=1);

namespace App\Services;

use App\Queries\Role\GetRoleQuery;
use Infra\Query\Role\RoleViewItem;
use Shared\Exceptions\Result;

interface IGetRoleUseCase
{
    /**
     * @return Result<RoleViewItem> The role read model, or 404 when not found.
     */
    public function execute(GetRoleQuery $query): Result;
}
