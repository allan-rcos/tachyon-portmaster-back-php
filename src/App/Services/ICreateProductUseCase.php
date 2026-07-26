<?php

declare(strict_types=1);

namespace App\Services;

use App\Commands\Product\CreateProductCommand;
use Domain\Models\IProduct;
use Shared\Exceptions\Result;

interface ICreateProductUseCase
{
    /**
     * @return Result<IProduct> The created product, or a validation/infra failure.
     */
    public function execute(CreateProductCommand $command): Result;
}
