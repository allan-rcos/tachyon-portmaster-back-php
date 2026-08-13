<?php

/**
 * Register Marker Group Use Case Contract.
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

use App\Commands\Marker\RegisterMarkerGroupCommand;
use RuntimeException;

/**
 * Adds one marker group to the runtime registry.
 *
 * The marker-group twin of {@see IRegisterPermissionUseCase}, called at
 * WorkerStart by whichever feature files markers under the group.
 *
 * @see RegisterMarkerGroupCommand What it takes.
 * @see \App\Services\Interno\RegisterMarkerGroupUseCase The implementation.
 * @see IRegisterPermissionUseCase The permission twin, and the reasoning.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IRegisterMarkerGroupUseCase
{
    /**
     * Validates the group through the domain table module and adds it to the
     * runtime registry, returning its **slug** — the only part a caller keeps.
     *
     * Returns a bare string rather than a `Result` for the same reason
     * {@see IRegisterPermissionUseCase::execute()} does: it is called at
     * WorkerStart, where a failure is not a runtime condition to branch on. A
     * malformed slug is a coding mistake, and a server that booted without the
     * group would answer every request that depends on it with a 404 — better to
     * refuse to start.
     *
     * Idempotent by slug, like its permission twin.
     *
     * @param  RegisterMarkerGroupCommand  $command  Carries the slug, and
     *                                               nothing else.
     * @return string The registered slug, to keep on the declaring feature.
     *
     * @throws RuntimeException when the metadata is invalid or the registry write fails.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function execute(RegisterMarkerGroupCommand $command): string;
}
