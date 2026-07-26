<?php

namespace Infra\Repository;

use Domain\Models\IProduct;
use Shared\Exceptions\Result;

interface IProductRepository
{
    /**
     * @return Result<IProduct> The product, or failure (404) when not found.
     */
    public function findById(string $id): Result;

    /**
     * @return Result<null>
     */
    public function insert(IProduct $product): Result;

    /**
     * @return Result<null>
     */
    public function update(IProduct $product): Result;

    /**
     * @return Result<null>
     */
    public function delete(string $id): Result;
}
