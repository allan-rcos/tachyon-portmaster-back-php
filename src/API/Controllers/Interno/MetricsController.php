<?php

declare(strict_types=1);

namespace API\Controllers\Interno;

use API\Controllers\IMetricsController;
use API\Controllers\ResolvesCaller;
use API\Fbs\Metrics\MetricsResponseProxy;
use API\Fbs\Metrics\OccupancyDivisionProxy;
use API\Http\ApiResponse;
use API\Http\ProblemResponse;
use App\Queries\Metrics\GetMetricsQuery;
use App\Services\IGetMetricsUseCase;
use Infra\Query\Metrics\MetricsView;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class MetricsController implements IMetricsController
{
    use ResolvesCaller;

    public function __construct(
        private IGetMetricsUseCase $getMetrics,
    ) {
    }

    public function get(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($caller);
        }
        $context = $caller->getValue();

        // No permission check here: `metrics:read` is the use case's business,
        // and its 403 arrives through the same failure path as any other error.
        $result = $this->getMetrics->execute(new GetMetricsQuery($context));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        /** @var MetricsView $view */
        $view = $result->getValue();

        return ApiResponse::body(new MetricsResponseProxy(
            activeContainers: $view->activeContainers,
            totalContainers: $view->totalContainers,
            yardLoad: $view->yardLoad,
            registeredProducts: $view->registeredProducts,
            occupancyDivision: new OccupancyDivisionProxy(
                empty: $view->occupancy->empty,
                loading: $view->occupancy->loading,
                sealed: $view->occupancy->sealed,
                inTransit: $view->occupancy->inTransit,
            ),
        ));
    }
}
