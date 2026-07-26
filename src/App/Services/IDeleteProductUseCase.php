<?php

declare(strict_types=1);

namespace App\Services;

use App\Commands\Product\DeleteProductCommand;
use Shared\Exceptions\Result;

interface IDeleteProductUseCase
{
    /**
     * @return Result<null> Void on success, 404 when the product does not exist.
     */
    public function execute(DeleteProductCommand $command): Result;
}
