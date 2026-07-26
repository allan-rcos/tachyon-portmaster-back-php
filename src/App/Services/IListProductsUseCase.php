<?php

declare(strict_types=1);

namespace App\Services;

use App\Queries\Product\ListProductsQuery;
use Infra\Query\Product\ProductListView;
use Shared\Exceptions\Result;

interface IListProductsUseCase
{
    /**
     * @return Result<ProductListView>
     */
    public function execute(ListProductsQuery $query): Result;
}
