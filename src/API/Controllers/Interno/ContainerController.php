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
use API\Http\ApiResponse;
use API\Http\ProblemResponse;
use API\Negociation\DTO\Container\CargoManifestItemX;
use API\Negociation\DTO\Container\ContainerCreateXRequestFactory;
use API\Negociation\DTO\Container\ContainerListXResponse;
use API\Negociation\DTO\Container\ContainerListXResponseFactory;
use API\Negociation\DTO\Container\ContainerSummaryListXResponse;
use API\Negociation\DTO\Container\ContainerSummaryListXResponseFactory;
use API\Negociation\DTO\Container\ContainerSummaryXResponse;
use API\Negociation\DTO\Container\ContainerUpdateXRequestFactory;
use API\Negociation\DTO\Container\ContainerXResponse;
use API\Negociation\DTO\Container\ContainerXResponseFactory;
use API\Negociation\DTO\Container\TelemetryLogItemX;
use API\Negociation\IAcceptsStrategy;
use API\Negociation\IContentTypeStrategy;
use App\Commands\Container\CreateContainerCommand;
use App\Commands\Container\DeleteContainerCommand;
use App\Commands\Container\DispatchContainerCommand;
use App\Commands\Container\SealContainerCommand;
use App\Commands\Container\UpdateContainerCommand;
use App\Queries\Container\GetContainerQuery;
use App\Queries\Container\ListContainerSummariesQuery;
use App\Queries\Container\ListContainersQuery;
use App\Services\ICreateContainerUseCase;
use App\Services\IDeleteContainerUseCase;
use App\Services\IDispatchContainerUseCase;
use App\Services\IGetContainerUseCase;
use App\Services\IListContainerSummariesUseCase;
use App\Services\IListContainersUseCase;
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
     * @param  IContentTypeStrategy  $contentType  Decodes the request bodies.
             * @param  IAcceptsStrategy  $accepts  Renders the response bodies.
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
        private IContentTypeStrategy $contentType,
        private IAcceptsStrategy $accepts,
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
     * @return ResponseInterface A `ContainerListXResponse`, or a problem
     *                           document.
     *
     * @copyright 2026 Tachyon
     */
    public function list(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $caller);
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
            return ProblemResponse::fromResult($this->accepts, $result);
        }

        /** @var ContainerListView $view */
        $view = $result->getValue();

        $data = [];
        foreach ($view->items as $item) {
            $data[] = $this->containerResponse($item);
        }

        $response = ApiResponse::body($this->accepts, new ContainerListXResponseFactory(new ContainerListXResponse(
            data: $data,
            nextCursor: $view->nextCursor,
            total: $view->total,
        )));

        return $response->isSuccess()
            ? $response->getValue()
            : ProblemResponse::fromResult($this->accepts, $response);
    }

    /**
     * Renders a page of containers with their cargo and recent telemetry.
     *
     * Takes an optional `id`, which narrows the page to one container — the
     * detail view of a container is the same document as one row of this list,
     * so it is the same endpoint rather than a second one.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface A `ContainerSummaryListXResponse`, or a
     *                           problem document.
     *
     * @copyright 2026 Tachyon
     */
    public function summary(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $caller);
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
            return ProblemResponse::fromResult($this->accepts, $result);
        }

        /** @var ContainerSummaryListView $view */
        $view = $result->getValue();

        $data = [];
        foreach ($view->items as $item) {
            $data[] = $this->summaryResponse($item);
        }

        $response = ApiResponse::body($this->accepts, new ContainerSummaryListXResponseFactory(new ContainerSummaryListXResponse(
            data: $data,
            nextCursor: $view->nextCursor,
            total: $view->total,
        )));

        return $response->isSuccess()
            ? $response->getValue()
            : ProblemResponse::fromResult($this->accepts, $response);
    }

    /**
     * Registers a container.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface A `ContainerXResponse` with 201, or a
     *                           problem document.
     *
     * @copyright 2026 Tachyon
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $caller);
        }
        $context = $caller->getValue();

        $decoded = $this->contentType->execute($request->getBody(), new ContainerCreateXRequestFactory());
        if (!$decoded->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $decoded);
        }

        $body = $decoded->getValue();
        $result = $this->createContainer->execute(new CreateContainerCommand(
            context: $context,
            code: $body->code ?? '',
            maxCapacity: $body->maxCapacity,
        ));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $result);
        }

        /** @var IContainer $container */
        $container = $result->getValue();

        $response = ApiResponse::body($this->accepts, new ContainerXResponseFactory($this->fromModel($container)), 201);

        return $response->isSuccess()
            ? $response->getValue()
            : ProblemResponse::fromResult($this->accepts, $response);
    }

    /**
     * Renders one container by its path id.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface A `ContainerXResponse`, or a problem
     *                           document — 404 when nothing matches the id.
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

        $result = $this->getContainer->execute(new GetContainerQuery($context, $this->pathId($request)));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $result);
        }

        /** @var ContainerViewItem $item */
        $item = $result->getValue();

        $response = ApiResponse::body($this->accepts, new ContainerXResponseFactory($this->containerResponse($item)));

        return $response->isSuccess()
            ? $response->getValue()
            : ProblemResponse::fromResult($this->accepts, $response);
    }

    /**
     * Updates a container's capacity.
     *
     * Its code is not among the fields: a container's code identifies the
     * physical unit, and changing it would rename a thing rather than edit it.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface A `ContainerXResponse`, or a problem
     *                           document — 404 when nothing matches the id.
     *
     * @copyright 2026 Tachyon
     */
    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $caller);
        }
        $context = $caller->getValue();

        $decoded = $this->contentType->execute($request->getBody(), new ContainerUpdateXRequestFactory());
        if (!$decoded->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $decoded);
        }

        $body = $decoded->getValue();
        $result = $this->updateContainer->execute(new UpdateContainerCommand(
            context: $context,
            id: $this->pathId($request),
            maxCapacity: $body->maxCapacity,
        ));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $result);
        }

        /** @var IContainer $container */
        $container = $result->getValue();

        $response = ApiResponse::body($this->accepts, new ContainerXResponseFactory($this->fromModel($container)));

        return $response->isSuccess()
            ? $response->getValue()
            : ProblemResponse::fromResult($this->accepts, $response);
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
            return ProblemResponse::fromResult($this->accepts, $caller);
        }
        $context = $caller->getValue();

        $result = $this->deleteContainer->execute(new DeleteContainerCommand($context, $this->pathId($request)));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $result);
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
            return ProblemResponse::fromResult($this->accepts, $caller);
        }
        $context = $caller->getValue();

        $result = $this->sealContainer->execute(new SealContainerCommand($context, $this->pathId($request)));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $result);
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
            return ProblemResponse::fromResult($this->accepts, $caller);
        }
        $context = $caller->getValue();

        $result = $this->dispatchContainer->execute(new DispatchContainerCommand($context, $this->pathId($request)));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $result);
        }

        return ApiResponse::noContent();
    }

    /**
     * The response message for a container read out of a view.
     *
     * @param  ContainerViewItem  $item  One row of a list or single-read query.
     * @return ContainerXResponse Ready to serialize.
     *
     * @copyright 2026 Tachyon
     */
    private function containerResponse(ContainerViewItem $item): ContainerXResponse
    {
        return new ContainerXResponse(
            id: $item->id,
            code: $item->code,
            currentWeight: $item->currentWeight,
            maxCapacity: $item->maxCapacity,
            status: $item->status,
        );
    }

    /**
     * The same message, from the domain model a write returns.
     *
     * Twinned with {@see containerResponse()} because a view row and a model
     * carry the same five fields under no common type.
     *
     * @param  IContainer  $container  The container as a write left it.
     * @return ContainerXResponse Ready to serialize.
     *
     * @copyright 2026 Tachyon
     */
    private function fromModel(IContainer $container): ContainerXResponse
    {
        return new ContainerXResponse(
            id: $container->id,
            code: $container->code,
            currentWeight: $container->currentWeight,
            maxCapacity: $container->maxCapacity,
            status: $container->status,
        );
    }

    /**
     * The summary message: a container plus its cargo and recent telemetry.
     *
     * @param  ContainerSummaryViewItem  $item  One row of the summary query.
     * @return ContainerSummaryXResponse Ready to serialize.
     *
     * @copyright 2026 Tachyon
     */
    private function summaryResponse(ContainerSummaryViewItem $item): ContainerSummaryXResponse
    {
        $manifest = [];
        foreach ($item->manifest as $cargo) {
            $manifest[] = new CargoManifestItemX(
                productId: $cargo->productId,
                productName: $cargo->productName,
                quantity: $cargo->quantity,
                weight: $cargo->weight,
            );
        }

        $logs = [];
        foreach ($item->recentLogs as $log) {
            $logs[] = new TelemetryLogItemX(
                id: $log->id,
                event: $log->event,
                description: $log->description,
                timestamp: $log->timestamp,
            );
        }

        return new ContainerSummaryXResponse(
            container: $this->containerResponse($item->container),
            manifest: $manifest,
            recentLogs: $logs,
        );
    }
}
