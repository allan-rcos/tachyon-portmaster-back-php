<?php

/**
 * Register Permission Use Case Contract.
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

use App\Commands\Permission\RegisterPermissionCommand;
use RuntimeException;

/**
 * Adds one permission to the runtime registry.
 *
 * The only use case reached from another's constructor rather than from a
 * controller, and the reason a permission needs no list maintained anywhere: a
 * use case declares its own, at WorkerStart, and that declaration is the
 * registration.
 *
 * @see RegisterPermissionCommand What it takes.
 * @see \App\Services\Interno\RegisterPermissionUseCase The implementation.
 * @see \App\Security\AuthorizesWithPermission What calls it.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
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
     * Idempotent by slug, which is what lets four workers declare the same
     * permission at boot without duplicating it.
     *
     * @param  RegisterPermissionCommand  $command  Carries the slug, and nothing
     *                                              else.
     * @return string The registered slug, to keep on the declaring use case.
     *
     * @throws RuntimeException when the metadata is invalid or the registry is full.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function execute(RegisterPermissionCommand $command): string;
}
