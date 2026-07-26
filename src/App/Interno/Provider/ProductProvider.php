<?php

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

final class ProductProvider extends FeatureProvider
{
    public function listProductsUseCase(): IListProductsUseCase
    {
        return new ListProductsUseCase($this->infra->queryRepository(), $this->registrar());
    }

    public function createProductUseCase(): ICreateProductUseCase
    {
        return new CreateProductUseCase(
            $this->infra->unitOfWork(),
            $this->infra->productRepository(),
            $this->domain->productTM(),
            $this->registrar(),
        );
    }

    public function getProductUseCase(): IGetProductUseCase
    {
        return new GetProductUseCase($this->infra->queryRepository(), $this->registrar());
    }

    public function updateProductUseCase(): IUpdateProductUseCase
    {
        return new UpdateProductUseCase(
            $this->infra->unitOfWork(),
            $this->infra->productRepository(),
            $this->domain->productTM(),
            $this->registrar(),
        );
    }

    public function deleteProductUseCase(): IDeleteProductUseCase
    {
        return new DeleteProductUseCase(
            $this->infra->unitOfWork(),
            $this->infra->productRepository(),
            $this->registrar(),
        );
    }
}
