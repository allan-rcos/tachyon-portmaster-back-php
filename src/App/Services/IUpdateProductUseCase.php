<?php

declare(strict_types=1);

namespace App\Services;

use App\Commands\Product\UpdateProductCommand;
use Domain\Models\IProduct;
use Shared\Exceptions\Result;

interface IUpdateProductUseCase
{
    /**
     * @return Result<IProduct> The updated product, or 404 / validation / infra failure.
     */
    public function execute(UpdateProductCommand $command): Result;
}
