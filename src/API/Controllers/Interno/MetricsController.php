<?php

/**
 * Metrics Controller.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Controllers\Interno;

use API\Controllers\IMetricsController;
use API\Controllers\ResolvesCaller;
use API\Http\ApiResponse;
use API\Http\ProblemResponse;
use API\Negociation\DTO\Metrics\MetricsXResponse;
use API\Negociation\DTO\Metrics\MetricsXResponseFactory;
use API\Negociation\DTO\Metrics\OccupancyDivisionX;
use API\Negociation\IAcceptsStrategy;
use App\Queries\Metrics\GetMetricsQuery;
use App\Services\IGetMetricsUseCase;
use Infra\Query\Metrics\MetricsView;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Operational metrics endpoint.
 *
 * @see IMetricsController The contract this implements.
 * @see ProductController The action shape this follows.
 * @uses IGetMetricsUseCase Produces the snapshot.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class MetricsController implements IMetricsController
{
    use ResolvesCaller;

    /**
     * @param  IGetMetricsUseCase  $getMetrics  Backs {@see get()}.
     * @param  IAcceptsStrategy  $accepts  Renders the response bodies.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IGetMetricsUseCase $getMetrics,
        private IAcceptsStrategy $accepts,
    ) {
    }

    /**
     * Renders the current metrics snapshot.
     *
     * Takes no parameters at all — the snapshot is of the whole yard, and there
     * is nothing to filter or page through.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface A `MetricsXResponse`, or a problem document.
     *
     * @copyright 2026 Tachyon
     */
    public function get(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $caller);
        }
        $context = $caller->getValue();

        // No permission check here: `metrics:read` is the use case's business,
        // and its 403 arrives through the same failure path as any other error.
        $result = $this->getMetrics->execute(new GetMetricsQuery($context));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $result);
        }

        /** @var MetricsView $view */
        $view = $result->getValue();

        $response = ApiResponse::body($this->accepts, new MetricsXResponseFactory(new MetricsXResponse(
            activeContainers: $view->activeContainers,
            totalContainers: $view->totalContainers,
            yardLoad: $view->yardLoad,
            registeredProducts: $view->registeredProducts,
            occupancyDivision: new OccupancyDivisionX(
                empty: $view->occupancy->empty,
                loading: $view->occupancy->loading,
                sealed: $view->occupancy->sealed,
                inTransit: $view->occupancy->inTransit,
            ),
        )));

        return $response->isSuccess()
            ? $response->getValue()
            : ProblemResponse::fromResult($this->accepts, $response);
    }
}
