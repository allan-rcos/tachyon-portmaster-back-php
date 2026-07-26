<?php

declare(strict_types=1);

namespace App\Services;

use App\Queries\Container\ListContainerSummariesQuery;
use Infra\Query\Container\ContainerSummaryListView;
use Shared\Exceptions\Result;

interface IListContainerSummariesUseCase
{
    /**
     * @return Result<ContainerSummaryListView>
     */
    public function execute(ListContainerSummariesQuery $query): Result;
}
