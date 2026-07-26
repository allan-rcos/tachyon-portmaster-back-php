<?php

declare(strict_types=1);

namespace App\Services;

use App\Queries\User\ListUsersQuery;
use Infra\Query\User\UserListView;
use Shared\Exceptions\Result;

interface IListUsersUseCase
{
    /**
     * @return Result<UserListView>
     */
    public function execute(ListUsersQuery $query): Result;
}
