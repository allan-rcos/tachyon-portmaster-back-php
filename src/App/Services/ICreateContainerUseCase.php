<?php

/**
 * Create Container Use Case Contract.
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

use App\Commands\Container\CreateContainerCommand;
use Domain\Models\IContainer;
use Shared\Exceptions\Result;

/**
 * Creates an empty container.
 *
 * Follows the write shape documented on
 * {@see \App\Services\Interno\CreateProductUseCase}: authorize, begin, build,
 * persist, commit, rolling back on every failure after the boundary opened.
 *
 * Guarded by `container:create`.
 *
 * @see CreateContainerCommand What it takes.
 * @see \App\Services\Interno\CreateContainerUseCase The implementation.
 * @see \App\Services\Interno\CreateProductUseCase The shape.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface ICreateContainerUseCase
{
    /**
     * Creates the container described by the command.
     *
     * @param  CreateContainerCommand  $command  Carries the caller, the code and
     *                                           the capacity.
     * @return Result<IContainer> The created container, empty and unsealed; a
     *                            403, 422 or 500 failure.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function execute(CreateContainerCommand $command): Result;
}
