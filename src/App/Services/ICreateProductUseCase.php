<?php

/**
 * Create Product Use Case Contract.
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

use App\Commands\Product\CreateProductCommand;
use Domain\Models\IProduct;
use Shared\Exceptions\Result;

/**
 * Creates a product.
 *
 * One of the write use cases, all of which share the shape documented on
 * {@see \App\Services\Interno\CreateProductUseCase}: authorize, begin, build,
 * persist, commit, rolling back on every failure after the boundary opened.
 *
 * Guarded by `product:create`, which this use case declares at WorkerStart.
 *
 * @see CreateProductCommand What it takes.
 * @see \App\Services\Interno\CreateProductUseCase The implementation, and the shape.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface ICreateProductUseCase
{
    /**
     * Creates the product described by the command.
     *
     * @param  CreateProductCommand  $command  Carries the caller and the
     *                                         product's fields.
     * @return Result<IProduct> The created product; a 403, 422 or 500 failure,
     *                          passed through from wherever it arose.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function execute(CreateProductCommand $command): Result;
}
