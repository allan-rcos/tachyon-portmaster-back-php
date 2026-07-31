<?php

/**
 * Container Controller.
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

use API\Controllers\IContainerController;
use API\Controllers\ResolvesCaller;
use API\Fbs\Container\CargoManifestItemProxy;
use API\Fbs\Container\ContainerCreateRequestProxy;
use API\Fbs\Container\ContainerListResponseProxy;
use API\Fbs\Container\ContainerResponseProxy;
use API\Fbs\Container\ContainerSummaryListResponseProxy;
use API\Fbs\Container\ContainerSummaryResponseProxy;
use API\Fbs\Container\ContainerUpdateRequestProxy;
use API\Fbs\Container\TelemetryLogItemProxy;
use API\Http\ApiResponse;
use API\Http\ProblemResponse;
use App\Commands\Container\CreateContainerCommand;
use App\Commands\Container\DeleteContainerCommand;
use App\Commands\Container\DispatchContainerCommand;
use App\Commands\Container\SealContainerCommand;
use App\Commands\Container\UpdateContainerCommand;
use App\Queries\Container\GetContainerQuery;
use App\Queries\Container\ListContainersQuery;
use App\Queries\Container\ListContainerSummariesQuery;
use App\Services\ICreateContainerUseCase;
use App\Services\IDeleteContainerUseCase;
use App\Services\IDispatchContainerUseCase;
use App\Services\IGetContainerUseCase;
use App\Services\IListContainersUseCase;
use App\Services\IListContainerSummariesUseCase;
use App\Services\ISealContainerUseCase;
use App\Services\IUpdateContainerUseCase;
use Domain\Models\IContainer;
use Infra\Query\Container\ContainerListView;
use Infra\Query\Container\ContainerSummaryListView;
use Infra\Query\Container\ContainerSummaryViewItem;
use Infra\Query\Container\ContainerViewItem;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Container endpoints.
 *
 * The two transitions answer 204 rather than the moved container: the caller
 * asked for a state change, not for a document, and either it happened or a 409
 * says why it could not.
 *
 * @see IContainerController The contract this implements.
 * @see ProductController The action shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class ContainerController implements IContainerController
{
    use ResolvesCaller;

    /**
     * @param  IListContainersUseCase  $listContainers  Backs {@see list()}.
     * @param  ICreateContainerUseCase  $createContainer  Backs {@see create()}.
     * @param  IListContainerSummariesUseCase  $listSummaries  Backs
     *                                                         {@see summary()}.
     * @param  IGetContainerUseCase  $getContainer  Backs {@see get()}.
     * @param  IUpdateContainerUseCase  $updateContainer  Backs {@see update()}.
     * @param  IDeleteContainerUseCase  $deleteContainer  Backs {@see delete()}.
     * @param  ISealContainerUseCase  $sealContainer  Backs {@see seal()}.
     * @param  IDispatchContainerUseCase  $dispatchContainer  Backs
     *                                                        {@see dispatch()}.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IListContainersUseCase $listContainers,
        private ICreateContainerUseCase $createContainer,
        private IListContainerSummariesUseCase $listSummaries,
        private IGetContainerUseCase $getContainer,
        private IUpdateContainerUseCase $updateContainer,
        private IDeleteContainerUseCase $deleteContainer,
        private ISealContainerUseCase $sealContainer,
        private IDispatchContainerUseCase $dispatchContainer,
    ) {
    }

    /**
     * Renders a page of containers.
     *
     * Two status filters, not one: `status` matches a single state, `status_in`
     * a comma-separated set — which is what a board showing everything except
     * dispatched containers needs.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface A `ContainerListResponseProxy`, or a problem
     *                           document.
     *
     * @copyright 2026 Tachyon
     */
    public function list(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($caller);
        }
        $context = $caller->getValue();

        $params = $request->getQueryParams();
        $result = $this->listContainers->execute(new ListContainersQuery(
            context: $context,
            cursor: isset($params['cursor']) && is_string($params['cursor']) ? $params['cursor'] : null,
            limit: isset($params['limit']) && is_numeric($params['limit']) ? (int) $params['limit'] : null,
            search: isset($params['search']) && is_string($params['search']) ? $params['search'] : null,
            status: isset($params['status']) && is_string($params['status']) ? $params['status'] : null,
            statusIn: isset($params['status_in']) && is_string($params['status_in']) ? $params['status_in'] : null,
        ));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        /** @var ContainerListView $view */
        $view = $result->getValue();

        $data = [];
        foreach ($view->items as $item) {
            $data[] = $this->containerResponse($item);
        }

        return ApiResponse::body(new ContainerListResponseProxy(
            data: $data,
            nextCursor: $view->nextCursor,
            total: $view->total,
        ));
    }

    /**
     * Renders a page of containers with their cargo and recent telemetry.
     *
     * Takes an optional `id`, which narrows the page to one container — the
     * detail view of a container is the same document as one row of this list,
     * so it is the same endpoint rather than a second one.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface A `ContainerSummaryListResponseProxy`, or a
     *                           problem document.
     *
     * @copyright 2026 Tachyon
     */
    public function summary(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($caller);
        }
        $context = $caller->getValue();

        $params = $request->getQueryParams();
        $result = $this->listSummaries->execute(new ListContainerSummariesQuery(
            context: $context,
            id: isset($params['id']) && is_string($params['id']) && $params['id'] !== '' ? $params['id'] : null,
            cursor: isset($params['cursor']) && is_string($params['cursor']) ? $params['cursor'] : null,
            limit: isset($params['limit']) && is_numeric($params['limit']) ? (int) $params['limit'] : null,
        ));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        /** @var ContainerSummaryListView $view */
        $view = $result->getValue();

        $data = [];
        foreach ($view->items as $item) {
            $data[] = $this->summaryResponse($item);
        }

        return ApiResponse::body(new ContainerSummaryListResponseProxy(
            data: $data,
            nextCursor: $view->nextCursor,
            total: $view->total,
        ));
    }

    /**
     * Registers a container.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface A `ContainerResponseProxy` with 201, or a
     *                           problem document.
     *
     * @copyright 2026 Tachyon
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($caller);
        }
        $context = $caller->getValue();

        $body = ContainerCreateRequestProxy::fromStream($request->getBody());
        $result = $this->createContainer->execute(new CreateContainerCommand(
            context: $context,
            code: $body->code ?? '',
            maxCapacity: $body->maxCapacity,
        ));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        /** @var IContainer $container */
        $container = $result->getValue();

        return ApiResponse::body($this->fromModel($container), 201);
    }

    /**
     * Renders one container by its path id.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface A `ContainerResponseProxy`, or a problem
     *                           document — 404 when nothing matches the id.
     *
     * @copyright 2026 Tachyon
     */
    public function get(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($caller);
        }
        $context = $caller->getValue();

        $result = $this->getContainer->execute(new GetContainerQuery($context, $this->pathId($request)));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        /** @var ContainerViewItem $item */
        $item = $result->getValue();

        return ApiResponse::body($this->containerResponse($item));
    }

    /**
     * Updates a container's capacity.
     *
     * Its code is not among the fields: a container's code identifies the
     * physical unit, and changing it would rename a thing rather than edit it.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface A `ContainerResponseProxy`, or a problem
     *                           document — 404 when nothing matches the id.
     *
     * @copyright 2026 Tachyon
     */
    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($caller);
        }
        $context = $caller->getValue();

        $body = ContainerUpdateRequestProxy::fromStream($request->getBody());
        $result = $this->updateContainer->execute(new UpdateContainerCommand(
            context: $context,
            id: $this->pathId($request),
            maxCapacity: $body->maxCapacity,
        ));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        /** @var IContainer $container */
        $container = $result->getValue();

        return ApiResponse::body($this->fromModel($container));
    }

    /**
     * Removes the container at the path id.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface An empty 204, or a problem document.
     *
     * @copyright 2026 Tachyon
     */
    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($caller);
        }
        $context = $caller->getValue();

        $result = $this->deleteContainer->execute(new DeleteContainerCommand($context, $this->pathId($request)));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        return ApiResponse::noContent();
    }

    /**
     * Seals the container at the path id, closing it to further cargo.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface An empty 204, or a problem document — 409 when
     *                           the container is not in a state that can be
     *                           sealed.
     *
     * @copyright 2026 Tachyon
     */
    public function seal(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($caller);
        }
        $context = $caller->getValue();

        $result = $this->sealContainer->execute(new SealContainerCommand($context, $this->pathId($request)));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        return ApiResponse::noContent();
    }

    /**
     * Sends the container at the path id into transit, which is terminal.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface An empty 204, or a problem document — 409 when
     *                           the container is not sealed, or already gone.
     *
     * @copyright 2026 Tachyon
     */
    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($caller);
        }
        $context = $caller->getValue();

        $result = $this->dispatchContainer->execute(new DispatchContainerCommand($context, $this->pathId($request)));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        return ApiResponse::noContent();
    }

    /**
     * The response proxy for a container read out of a view.
     *
     * @param  ContainerViewItem  $item  One row of a list or single-read query.
     * @return ContainerResponseProxy Ready to serialize.
     *
     * @copyright 2026 Tachyon
     */
    private function containerResponse(ContainerViewItem $item): ContainerResponseProxy
    {
        return new ContainerResponseProxy(
            id: $item->id,
            code: $item->code,
            currentWeight: $item->currentWeight,
            maxCapacity: $item->maxCapacity,
            status: $item->status,
        );
    }

    /**
     * The same proxy, from the domain model a write returns.
     *
     * Twinned with {@see containerResponse()} because a view row and a model
     * carry the same five fields under no common type.
     *
     * @param  IContainer  $container  The container as a write left it.
     * @return ContainerResponseProxy Ready to serialize.
     *
     * @copyright 2026 Tachyon
     */
    private function fromModel(IContainer $container): ContainerResponseProxy
    {
        return new ContainerResponseProxy(
            id: $container->id,
            code: $container->code,
            currentWeight: $container->currentWeight,
            maxCapacity: $container->maxCapacity,
            status: $container->status,
        );
    }

    /**
     * The summary proxy: a container plus its cargo and recent telemetry.
     *
     * @param  ContainerSummaryViewItem  $item  One row of the summary query.
     * @return ContainerSummaryResponseProxy Ready to serialize.
     *
     * @copyright 2026 Tachyon
     */
    private function summaryResponse(ContainerSummaryViewItem $item): ContainerSummaryResponseProxy
    {
        $manifest = [];
        foreach ($item->manifest as $cargo) {
            $manifest[] = new CargoManifestItemProxy(
                productId: $cargo->productId,
                productName: $cargo->productName,
                quantity: $cargo->quantity,
                weight: $cargo->weight,
            );
        }

        $logs = [];
        foreach ($item->recentLogs as $log) {
            $logs[] = new TelemetryLogItemProxy(
                id: $log->id,
                event: $log->event,
                description: $log->description,
                timestamp: $log->timestamp,
            );
        }

        return new ContainerSummaryResponseProxy(
            container: $this->containerResponse($item->container),
            manifest: $manifest,
            recentLogs: $logs,
        );
    }
}
