<?php

declare(strict_types=1);

namespace App\Services;

use App\Queries\Role\ListRolesQuery;
use Infra\Query\Role\RoleListView;
use Shared\Exceptions\Result;

interface IListRolesUseCase
{
    /**
     * @return Result<RoleListView>
     */
    public function execute(ListRolesQuery $query): Result;
}
