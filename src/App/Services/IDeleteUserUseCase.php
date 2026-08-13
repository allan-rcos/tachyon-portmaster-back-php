<?php

/**
 * Delete User Use Case Contract.
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

use App\Commands\User\DeleteUserCommand;
use Shared\Exceptions\Result;

/**
 * Removes a user, and with them — by cascade — their role assignments.
 *
 * Follows the delete shape documented on {@see IDeleteProductUseCase}: load
 * first so an absent user is a 404, then delete.
 *
 * Guarded by `user:delete`.
 *
 * @see DeleteUserCommand What it takes.
 * @see \App\Services\Interno\DeleteUserUseCase The implementation.
 * @see IDeleteProductUseCase The shape.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IDeleteUserUseCase
{
    /**
     * Removes the user the command names.
     *
     * @param  DeleteUserCommand  $command  Carries the caller and the id.
     * @return Result<null> Void on success, 404 when the user does not exist; a
     *                      403 or 500 otherwise.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function execute(DeleteUserCommand $command): Result;
}
