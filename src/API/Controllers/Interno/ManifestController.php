<?php

declare(strict_types=1);

namespace API\Controllers\Interno;

use API\Controllers\IManifestController;
use API\Controllers\ResolvesCaller;
use API\Fbs\Container\ContainerResponseProxy;
use API\Fbs\Manifest\LoadItemRequestProxy;
use API\Fbs\Manifest\ManifestResponseProxy;
use API\Fbs\Manifest\UnloadItemRequestProxy;
use API\Http\ApiResponse;
use API\Http\ProblemResponse;
use App\Commands\Manifest\LoadItemCommand;
use App\Commands\Manifest\UnloadItemCommand;
use App\Services\ILoadItemUseCase;
use App\Services\IUnloadItemUseCase;
use Domain\Models\IContainer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ManifestController implements IManifestController
{
    use ResolvesCaller;

    public function __construct(
        private ILoadItemUseCase $loadItem,
        private IUnloadItemUseCase $unloadItem,
    ) {
    }

    public function loadItem(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($caller);
        }
        $context = $caller->getValue();

        $body = LoadItemRequestProxy::fromStream($request->getBody());
        $result = $this->loadItem->execute(new LoadItemCommand(
            context: $context,
            containerId: $body->containerId ?? '',
            productId: $body->productId ?? '',
            quantity: $body->quantity,
        ));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        /** @var IContainer $container */
        $container = $result->getValue();

        return ApiResponse::body($this->response('Item loaded successfully.', $container));
    }

    public function unloadItem(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($caller);
        }
        $context = $caller->getValue();

        $body = UnloadItemRequestProxy::fromStream($request->getBody());
        $result = $this->unloadItem->execute(new UnloadItemCommand(
            context: $context,
            containerId: $body->containerId ?? '',
            productId: $body->productId ?? '',
            quantity: $body->quantity,
        ));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        /** @var IContainer $container */
        $container = $result->getValue();

        return ApiResponse::body($this->response('Item unloaded successfully.', $container));
    }

    private function response(string $message, IContainer $container): ManifestResponseProxy
    {
        return new ManifestResponseProxy(
            message: $message,
            container: new ContainerResponseProxy(
                id: $container->id,
                code: $container->code,
                currentWeight: $container->currentWeight,
                maxCapacity: $container->maxCapacity,
                status: $container->status,
            ),
        );
    }
}
