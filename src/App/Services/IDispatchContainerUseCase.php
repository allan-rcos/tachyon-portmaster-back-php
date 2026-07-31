<?php

/**
 * Dispatch Container Use Case Contract.
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

use App\Commands\Container\DispatchContainerCommand;
use Shared\Exceptions\Result;

/**
 * Moves a sealed container into transit.
 *
 * A transition use case: load, ask the table module for the moved container,
 * persist, commit. The 409 comes from the domain refusing the move, not from any
 * check made here — this layer never decides which transitions are legal.
 *
 * Guarded by `container:dispatch`.
 *
 * @see DispatchContainerCommand What it takes.
 * @see \App\Services\Interno\DispatchContainerUseCase The implementation.
 * @see ISealContainerUseCase The transition before it.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IDispatchContainerUseCase
{
    /**
     * Dispatches the container the command names.
     *
     * @param  DispatchContainerCommand  $command  Carries the caller and the id.
     * @return Result<null> Void on success; 404 (missing) or 409 (not sealed); a
     *                      403 or 500 otherwise.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function execute(DispatchContainerCommand $command): Result;
}
