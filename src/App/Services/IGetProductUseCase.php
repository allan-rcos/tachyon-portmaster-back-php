<?php

declare(strict_types=1);

namespace App\Services;

use App\Queries\Product\GetProductQuery;
use Infra\Query\Product\ProductViewItem;
use Shared\Exceptions\Result;

interface IGetProductUseCase
{
    /**
     * @return Result<ProductViewItem> The product, or 404 when not found.
     */
    public function execute(GetProductQuery $query): Result;
}
