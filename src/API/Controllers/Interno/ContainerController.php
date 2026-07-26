<?php

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

final readonly class ContainerController implements IContainerController
{
    use ResolvesCaller;

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
