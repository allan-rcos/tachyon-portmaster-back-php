<?php

declare(strict_types=1);

namespace API\Controllers\Interno;

use API\Controllers\IProductController;
use API\Controllers\ResolvesCaller;
use API\Fbs\Product\ProductCreateRequestProxy;
use API\Fbs\Product\ProductListResponseProxy;
use API\Fbs\Product\ProductResponseProxy;
use API\Fbs\Product\ProductUpdateRequestProxy;
use API\Http\ApiResponse;
use API\Http\ProblemResponse;
use App\Commands\Product\CreateProductCommand;
use App\Commands\Product\DeleteProductCommand;
use App\Commands\Product\UpdateProductCommand;
use App\Queries\Product\GetProductQuery;
use App\Queries\Product\ListProductsQuery;
use App\Services\ICreateProductUseCase;
use App\Services\IDeleteProductUseCase;
use App\Services\IGetProductUseCase;
use App\Services\IListProductsUseCase;
use App\Services\IUpdateProductUseCase;
use Domain\Enums\RiskClass;
use Domain\Models\IProduct;
use Infra\Query\Product\ProductListView;
use Infra\Query\Product\ProductViewItem;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Product endpoints.
 *
 * The controller no longer decides authorization: it establishes the caller and
 * passes that context into the command/query. Each use case checks its own
 * permission and answers 403, which {@see ProblemResponse::fromResult()} maps
 * like any other failure.
 */
final readonly class ProductController implements IProductController
{
    use ResolvesCaller;

    public function __construct(
        private IListProductsUseCase $listProducts,
        private ICreateProductUseCase $createProduct,
        private IGetProductUseCase $getProduct,
        private IUpdateProductUseCase $updateProduct,
        private IDeleteProductUseCase $deleteProduct,
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
        $result = $this->listProducts->execute(new ListProductsQuery(
            context: $context,
            cursor: isset($params['cursor']) && is_string($params['cursor']) ? $params['cursor'] : null,
            limit: isset($params['limit']) && is_numeric($params['limit']) ? (int) $params['limit'] : null,
            search: isset($params['search']) && is_string($params['search']) ? $params['search'] : null,
        ));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        /** @var ProductListView $view */
        $view = $result->getValue();

        $data = [];
        foreach ($view->items as $item) {
            $data[] = $this->response($item->id, $item->name, $item->density, $item->riskClass);
        }

        return ApiResponse::body(new ProductListResponseProxy(
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

        $body = ProductCreateRequestProxy::fromStream($request->getBody());
        $result = $this->createProduct->execute(new CreateProductCommand(
            context: $context,
            name: $body->name ?? '',
            density: $body->density,
            riskClass: $body->riskClass,
        ));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        /** @var IProduct $product */
        $product = $result->getValue();

        return ApiResponse::body(
            $this->response($product->id, $product->name, $product->density, $product->riskClass),
            201,
        );
    }

    public function get(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($caller);
        }
        $context = $caller->getValue();

        $result = $this->getProduct->execute(new GetProductQuery($context, $this->pathId($request)));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        /** @var ProductViewItem $item */
        $item = $result->getValue();

        return ApiResponse::body($this->response($item->id, $item->name, $item->density, $item->riskClass));
    }

    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($caller);
        }
        $context = $caller->getValue();

        $body = ProductUpdateRequestProxy::fromStream($request->getBody());
        $result = $this->updateProduct->execute(new UpdateProductCommand(
            context: $context,
            id: $this->pathId($request),
            name: $body->name ?? '',
            density: $body->density,
            riskClass: $body->riskClass,
        ));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        /** @var IProduct $product */
        $product = $result->getValue();

        return ApiResponse::body($this->response($product->id, $product->name, $product->density, $product->riskClass));
    }

    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $caller = $this->caller();
        if (!$caller->isSuccess()) {
            return ProblemResponse::fromResult($caller);
        }
        $context = $caller->getValue();

        $result = $this->deleteProduct->execute(new DeleteProductCommand($context, $this->pathId($request)));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        return ApiResponse::noContent();
    }

    private function response(string $id, string $name, float $density, RiskClass $riskClass): ProductResponseProxy
    {
        return new ProductResponseProxy(id: $id, name: $name, density: $density, riskClass: $riskClass);
    }
}
