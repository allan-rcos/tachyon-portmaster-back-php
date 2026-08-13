<?php

/**
 * List Products Use Case Contract.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App\Services;

use App\Queries\Product\ListProductsQuery;
use Infra\Query\Product\ProductListView;
use Shared\Exceptions\Result;

/**
 * Lists products.
 *
 * One of the list use cases, all of which share the shape documented on
 * {@see \App\Services\Interno\ListProductsUseCase}: authorize, build the DQL,
 * return what the runner returns. No transaction, and no 404 for an empty page.
 *
 * Guarded by `product:read`, shared with {@see IGetProductUseCase}.
 *
 * @see ListProductsQuery What it takes.
 * @see \App\Services\Interno\ListProductsUseCase The implementation, and the shape.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IListProductsUseCase
{
    /**
     * Reads the page the query asks for.
     *
     * @param  ListProductsQuery  $query  Carries the caller and the paging and
     *                                    filter parameters.
     * @return Result<ProductListView> The page, empty when nothing matched; a
     *                                 403 or 500 failure.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function execute(ListProductsQuery $query): Result;
}
