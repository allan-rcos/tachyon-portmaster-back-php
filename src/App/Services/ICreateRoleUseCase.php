<?php

/**
 * Create Role Use Case Contract.
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

use App\Commands\Role\CreateRoleCommand;
use Domain\Models\IRole;
use Shared\Exceptions\Result;

/**
 * Creates a role granting a set of permissions.
 *
 * Follows the write shape documented on
 * {@see \App\Services\Interno\CreateProductUseCase}.
 *
 * Guarded by `role:create`.
 *
 * @see CreateRoleCommand What it takes.
 * @see \App\Services\Interno\CreateRoleUseCase The implementation.
 * @see \App\Services\Interno\CreateProductUseCase The shape.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface ICreateRoleUseCase
{
    /**
     * Creates the role described by the command.
     *
     * @param  CreateRoleCommand  $command  Carries the caller, the name and the
     *                                      permission slugs.
     * @return Result<IRole> The created role; a 403, 422 or 500 failure.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function execute(CreateRoleCommand $command): Result;
}
