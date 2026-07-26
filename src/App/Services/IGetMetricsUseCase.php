<?php

declare(strict_types=1);

namespace App\Services;

use App\Queries\Metrics\GetMetricsQuery;
use Infra\Query\Metrics\MetricsView;
use Shared\Exceptions\Result;

interface IGetMetricsUseCase
{
    /**
     * @return Result<MetricsView>
     */
    public function execute(GetMetricsQuery $query): Result;
}
