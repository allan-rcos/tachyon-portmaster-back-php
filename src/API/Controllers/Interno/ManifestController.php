<?php

/**
 * Manifest Controller.
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

/**
 * Manifest (cargo) endpoints.
 *
 * Both actions answer with the affected **container**, not with the manifest
 * line: what a caller needs after moving cargo is the container's new weight
 * and status, which is what decides whether the next move will be accepted.
 *
 * @see IManifestController The contract this implements.
 * @see ProductController The action shape this follows.
 * @uses ILoadItemUseCase Adds cargo.
 * @uses IUnloadItemUseCase Removes it.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class ManifestController implements IManifestController
{
    use ResolvesCaller;

    /**
     * @param  ILoadItemUseCase  $loadItem  Backs {@see loadItem()}.
     * @param  IUnloadItemUseCase  $unloadItem  Backs {@see unloadItem()}.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private ILoadItemUseCase $loadItem,
        private IUnloadItemUseCase $unloadItem,
    ) {
    }

    /**
     * Loads cargo into a container.
     *
     * Both ids come from the body rather than the path, since the action names
     * a container and a product and neither is the resource being addressed.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface A `ManifestResponseProxy` carrying the updated
     *                           container, or a problem document — 409 when the
     *                           container will not take the cargo.
     *
     * @copyright 2026 Tachyon
     */
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

    /**
     * Unloads cargo from a container.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface A `ManifestResponseProxy` carrying the updated
     *                           container, or a problem document — 409 when the
     *                           container will not give the cargo up.
     *
     * @copyright 2026 Tachyon
     */
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

    /**
     * The response proxy both actions answer with.
     *
     * @param  string  $message  Which move succeeded.
     * @param  IContainer  $container  The container as it now stands.
     * @return ManifestResponseProxy Ready to serialize.
     *
     * @copyright 2026 Tachyon
     */
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
