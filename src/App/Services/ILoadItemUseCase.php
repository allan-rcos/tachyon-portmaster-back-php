<?php

/**
 * Load Item Use Case Contract.
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

use App\Commands\Manifest\LoadItemCommand;
use Domain\Models\IContainer;
use Shared\Exceptions\Result;

/**
 * Loads a quantity of a product into a container.
 *
 * Follows the write shape documented on
 * {@see \App\Services\Interno\CreateProductUseCase}, but with more to load
 * first: the container, the product and any existing cargo line all have to be
 * read before the table module can decide whether the load fits.
 *
 * Persisting is two writes — the cargo line and the container's new weight —
 * plus a telemetry entry, which is exactly why the boundary matters here.
 *
 * Guarded by `manifest:load`.
 *
 * @see LoadItemCommand What it takes.
 * @see \App\Services\Interno\LoadItemUseCase The implementation.
 * @see IUnloadItemUseCase The inverse.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface ILoadItemUseCase
{
    /**
     * Loads the quantity the command names.
     *
     * @param  LoadItemCommand  $command  Carries the caller, both ids and the
     *                                    quantity.
     * @return Result<IContainer> The container's new state after the load; 404
     *                            when the container or product is missing, 409
     *                            when the container will not take it, 422 when
     *                            the quantity is refused.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function execute(LoadItemCommand $command): Result;
}
