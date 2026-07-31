<?php

/**
 * Seal Container Use Case Contract.
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

use App\Commands\Container\SealContainerCommand;
use Shared\Exceptions\Result;

/**
 * Seals a container, closing it for further loading.
 *
 * A transition use case, shaped like {@see IDispatchContainerUseCase}: load, ask
 * the table module for the moved container, persist, commit. The 409 comes from
 * the domain refusing the move.
 *
 * Guarded by `container:seal`.
 *
 * @see SealContainerCommand What it takes.
 * @see \App\Services\Interno\SealContainerUseCase The implementation.
 * @see IDispatchContainerUseCase The transition after it.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface ISealContainerUseCase
{
    /**
     * Seals the container the command names.
     *
     * @param  SealContainerCommand  $command  Carries the caller and the id.
     * @return Result<null> Void on success; 404 (missing) or 409
     *                      (preconditions); a 403 or 500 otherwise.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function execute(SealContainerCommand $command): Result;
}
