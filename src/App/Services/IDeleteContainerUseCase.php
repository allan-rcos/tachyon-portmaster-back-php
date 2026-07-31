<?php

/**
 * Delete Container Use Case Contract.
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

use App\Commands\Container\DeleteContainerCommand;
use Shared\Exceptions\Result;

/**
 * Removes a container, and with it — by cascade — its manifest and telemetry.
 *
 * Follows the delete shape documented on {@see IDeleteProductUseCase}: load
 * first so an absent container is a 404, then delete.
 *
 * Guarded by `container:delete`.
 *
 * @see DeleteContainerCommand What it takes.
 * @see \App\Services\Interno\DeleteContainerUseCase The implementation.
 * @see IDeleteProductUseCase The shape.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IDeleteContainerUseCase
{
    /**
     * Removes the container the command names.
     *
     * @param  DeleteContainerCommand  $command  Carries the caller and the id.
     * @return Result<null> Void on success, 404 when the container does not
     *                      exist; a 403 or 500 otherwise.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function execute(DeleteContainerCommand $command): Result;
}
