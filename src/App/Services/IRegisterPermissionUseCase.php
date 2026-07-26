<?php

declare(strict_types=1);

namespace App\Services;

use App\Commands\Permission\RegisterPermissionCommand;
use RuntimeException;

interface IRegisterPermissionUseCase
{
    /**
     * Validates the permission through the domain table module and adds it to the
     * runtime registry, returning its **slug** — the only part a use case keeps.
     *
     * Returns a bare string rather than a `Result` because it is called from
     * constructors at WorkerStart, where a failure is never a runtime condition
     * to branch on: a malformed slug is a coding mistake, and the server should
     * refuse to boot rather than start with a permission that no role can ever
     * match.
     *
     * @throws RuntimeException when the metadata is invalid or the registry is full.
     */
    public function execute(RegisterPermissionCommand $command): string;
}
