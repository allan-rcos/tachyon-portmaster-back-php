<?php

declare(strict_types=1);

namespace App\Services;

use App\Queries\Container\ListContainersQuery;
use Infra\Query\Container\ContainerListView;
use Shared\Exceptions\Result;

interface IListContainersUseCase
{
    /**
     * @return Result<ContainerListView>
     */
    public function execute(ListContainersQuery $query): Result;
}
