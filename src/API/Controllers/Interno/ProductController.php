<?php

/**
 * Product Controller.
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

use API\Controllers\IProductController;
use API\Controllers\ResolvesCaller;
use API\Http\ApiResponse;
use API\Http\ProblemResponse;
use API\Negociation\DTO\Product\ProductCreateXRequest;
use API\Negociation\DTO\Product\ProductCreateXRequestFactory;
use API\Negociation\DTO\Product\ProductListXResponse;
use API\Negociation\DTO\Product\ProductListXResponseFactory;
use API\Negociation\DTO\Product\ProductUpdateXRequest;
use API\Negociation\DTO\Product\ProductUpdateXRequestFactory;
use API\Negociation\DTO\Product\ProductXResponse;
use API\Negociation\DTO\Product\ProductXResponseFactory;
use API\Negociation\IAcceptsStrategy;
use API\Negociation\IContentTypeStrategy;
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
 *
 * The **action shape**, written out once here and followed by every other
 * controller: resolve the caller, bail out as a problem response if there is
 * none, decode the body by handing its factory to the content-type strategy,
 * hand a command or query to the use case, bail out again on failure, and wrap
 * the value in a response factory for the accepts strategy to render.
 *
 * Four of those steps answer a {@see \Shared\Exceptions\Result}, and unwrapping
 * every one of them is the controller's job: it is the only place that knows
 * what a failure means for the response. A controller still holds no rules of
 * its own — its branches are "no caller", "the body could not be read", "the
 * use case said no" and "the answer could not be rendered", and all four end in
 * {@see ProblemResponse::fromResult()}. It decides no wire format either: both
 * strategies were chosen by the negotiation middleware, and it only uses them.
 *
 * @see IProductController The contract this implements.
 * @see ResolvesCaller Supplies `caller()` and `pathId()`.
 * @see IContentTypeStrategy How the body is decoded.
 * @see IAcceptsStrategy How the answer is rendered.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class ProductController implements IProductController
{
    use ResolvesCaller;

    /**
     * @param  IListProductsUseCase  $listProducts  Backs {@see list()}.
     * @param  ICreateProductUseCase  $createProduct  Backs {@see create()}.
     * @param  IGetProductUseCase  $getProduct  Backs {@see get()}.
     * @param  IUpdateProductUseCase  $updateProduct  Backs {@see update()}.
     * @param  IDeleteProductUseCase  $deleteProduct  Backs {@see delete()}.
     * @param  IContentTypeStrategy  $contentType  Decodes the request bodies.
             * @param  IAcceptsStrategy  $accepts  Renders the response bodies.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IListProductsUseCase $listProducts,
        private ICreateProductUseCase $createProduct,
        private IGetProductUseCase $getProduct,
        private IUpdateProductUseCase $updateProduct,
        private IDeleteProductUseCase $deleteProduct,
        private IContentTypeStrategy $contentType,
        private IAcceptsStrategy $accepts,
    ) {
    }

    /**
     * Reads `cursor`, `limit` and `search` off the query string and renders the
     * page.
     *
     * Each parameter is validated only as far as its type: anything of the
     * wrong shape becomes null and the query applies its own default, since a
     * malformed `limit` is not worth a 400.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface A `ProductListXResponse`, or a problem
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
        $result = $this->listProducts->execute(new ListProductsQuery(
            context: $context,
            cursor: isset($params['cursor']) && is_string($params['cursor']) ? $params['cursor'] : null,
            limit: isset($params['limit']) && is_numeric($params['limit']) ? (int) $params['limit'] : null,
            search: isset($params['search']) && is_string($params['search']) ? $params['search'] : null,
        ));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $result);
        }

        /** @var ProductListView $view */
        $view = $result->getValue();

        $data = [];
        foreach ($view->items as $item) {
            $data[] = $this->response($item->id, $item->name, $item->density, $item->riskClass);
        }

        $response = ApiResponse::body($this->accepts, new ProductListXResponseFactory(new ProductListXResponse(
            data: $data,
            nextCursor: $view->nextCursor,
            total: $view->total,
        )));

        return $response->isSuccess()
            ? $response->getValue()
            : ProblemResponse::fromResult($this->accepts, $response);
    }

    /**
     * Decodes a `ProductCreateXRequest` and registers the product.
     *
     * A missing `name` is passed on as an empty string rather than rejected
     * here: the table module owns that rule and answers 422 with every broken
     * field at once.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface A `ProductXResponse` with 201, or a problem
     *                           document.
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

        $decoded = $this->contentType->execute($request->getBody(), new ProductCreateXRequestFactory());
        if (!$decoded->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $decoded);
        }

        $body = $decoded->getValue();
        $result = $this->createProduct->execute(new CreateProductCommand(
            context: $context,
            name: $body->name ?? '',
            density: $body->density,
            riskClass: $body->riskClass,
        ));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $result);
        }

        /** @var IProduct $product */
        $product = $result->getValue();

        $response = ApiResponse::body(
            $this->accepts,
            new ProductXResponseFactory(
                $this->response($product->id, $product->name, $product->density, $product->riskClass),
            ),
            201,
        );

        return $response->isSuccess()
            ? $response->getValue()
            : ProblemResponse::fromResult($this->accepts, $response);
    }

    /**
     * Renders one product by its path id.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface A `ProductXResponse`, or a problem document
     *                           — 404 when nothing matches the id.
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

        $result = $this->getProduct->execute(new GetProductQuery($context, $this->pathId($request)));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $result);
        }

        /** @var ProductViewItem $item */
        $item = $result->getValue();

        $response = ApiResponse::body($this->accepts, new ProductXResponseFactory($this->response($item->id, $item->name, $item->density, $item->riskClass)));

        return $response->isSuccess()
            ? $response->getValue()
            : ProblemResponse::fromResult($this->accepts, $response);
    }

    /**
     * Decodes a `ProductUpdateXRequest` and replaces the product's fields.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface A `ProductXResponse`, or a problem document
     *                           — 404 when nothing matches the id.
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

        $decoded = $this->contentType->execute($request->getBody(), new ProductUpdateXRequestFactory());
        if (!$decoded->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $decoded);
        }

        $body = $decoded->getValue();
        $result = $this->updateProduct->execute(new UpdateProductCommand(
            context: $context,
            id: $this->pathId($request),
            name: $body->name ?? '',
            density: $body->density,
            riskClass: $body->riskClass,
        ));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $result);
        }

        /** @var IProduct $product */
        $product = $result->getValue();

        $response = ApiResponse::body($this->accepts, new ProductXResponseFactory($this->response($product->id, $product->name, $product->density, $product->riskClass)));

        return $response->isSuccess()
            ? $response->getValue()
            : ProblemResponse::fromResult($this->accepts, $response);
    }

    /**
     * Removes the product at the path id.
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

        $result = $this->deleteProduct->execute(new DeleteProductCommand($context, $this->pathId($request)));
        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($this->accepts, $result);
        }

        return ApiResponse::noContent();
    }

    /**
     * The response message, from either a domain model or a view row.
     *
     * The two carry the same four fields under the same names but share no
     * type, so this takes the fields rather than either object.
     *
     * @param  string  $id  Base62 product id.
     * @param  string  $name  Commercial name.
     * @param  float  $density  Kilograms per litre.
     * @param  RiskClass  $riskClass  Hazard classification.
     * @return ProductXResponse Ready to serialize.
     *
     * @copyright 2026 Tachyon
     */
    private function response(string $id, string $name, float $density, RiskClass $riskClass): ProductXResponse
    {
        return new ProductXResponse(id: $id, name: $name, density: $density, riskClass: $riskClass);
    }
}
