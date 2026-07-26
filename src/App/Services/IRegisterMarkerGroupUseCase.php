<?php

declare(strict_types=1);

namespace App\Services;

use App\Commands\Marker\RegisterMarkerGroupCommand;
use RuntimeException;

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
     * @throws RuntimeException when the metadata is invalid or the registry write fails.
     */
    public function execute(RegisterMarkerGroupCommand $command): string;
}
