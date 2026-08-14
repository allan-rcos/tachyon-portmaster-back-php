<?php

/**
 * Product Provider.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App\Interno\Provider;

use App\Services\ICreateProductUseCase;
use App\Services\IDeleteProductUseCase;
use App\Services\IGetProductUseCase;
use App\Services\IListProductsUseCase;
use App\Services\Interno\CreateProductUseCase;
use App\Services\Interno\DeleteProductUseCase;
use App\Services\Interno\GetProductUseCase;
use App\Services\Interno\ListProductsUseCase;
use App\Services\Interno\UpdateProductUseCase;
use App\Services\IUpdateProductUseCase;

/**
 * Builds the product feature's use cases.
 *
 * One of the per-feature slices of {@see \App\Interno\AppProvider}; see
 * {@see FeatureProvider} for why the wiring is split this way and why nothing
 * here is memoized.
 *
 * The reads take only the query runner, the writes take the boundary, the
 * repository and the table module — which is the wiring difference between the
 * two sides of CQRS made visible.
 *
 * @see FeatureProvider The base, and the registrar.
 * @see \App\Interno\AppProvider What re-exports these.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final class ProductProvider extends FeatureProvider
{
    /**
     * Builds the {@see IListProductsUseCase} implementation.
     *
     * @copyright 2026 Tachyon
     */
    public function listProductsUseCase(): IListProductsUseCase
    {
        return new ListProductsUseCase(
            $this->infra->queryRepository(),
            $this->infra->viewCacheRepository(),
            $this->events,
            $this->registrar(),
        );
    }

    /**
     * Builds the {@see ICreateProductUseCase} implementation.
     *
     * @copyright 2026 Tachyon
     */
    public function createProductUseCase(): ICreateProductUseCase
    {
        return new CreateProductUseCase(
            $this->infra->unitOfWork(),
            $this->infra->viewCacheRepository(),
            $this->infra->productRepository(),
            $this->domain->productTM(),
            $this->registrar(),
        );
    }

    /**
     * Builds the {@see IGetProductUseCase} implementation.
     *
     * @copyright 2026 Tachyon
     */
    public function getProductUseCase(): IGetProductUseCase
    {
        return new GetProductUseCase($this->infra->queryRepository(), $this->registrar());
    }

    /**
     * Builds the {@see IUpdateProductUseCase} implementation.
     *
     * @copyright 2026 Tachyon
     */
    public function updateProductUseCase(): IUpdateProductUseCase
    {
        return new UpdateProductUseCase(
            $this->infra->unitOfWork(),
            $this->infra->viewCacheRepository(),
            $this->infra->productRepository(),
            $this->domain->productTM(),
            $this->registrar(),
        );
    }

    /**
     * Builds the {@see IDeleteProductUseCase} implementation.
     *
     * @copyright 2026 Tachyon
     */
    public function deleteProductUseCase(): IDeleteProductUseCase
    {
        return new DeleteProductUseCase(
            $this->infra->unitOfWork(),
            $this->infra->viewCacheRepository(),
            $this->infra->productRepository(),
            $this->registrar(),
        );
    }
}
