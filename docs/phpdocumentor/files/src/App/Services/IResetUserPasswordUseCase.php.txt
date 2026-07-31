<?php

/**
 * Reset User Password Use Case Contract.
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

use App\Commands\User\ResetUserPasswordCommand;
use Shared\Exceptions\Result;

/**
 * Resets another user's password administratively.
 *
 * Follows the update shape documented on {@see IUpdateProductUseCase}.
 *
 * Unlike {@see IChangePasswordUseCase} it requires no current password — the
 * caller is not the owner and could not supply one. The permission is what
 * stands in for that proof, which is why the two are separate use cases rather
 * than one with an optional field.
 *
 * Guarded by `user:change-password`.
 *
 * @see ResetUserPasswordCommand What it takes.
 * @see \App\Services\Interno\ResetUserPasswordUseCase The implementation.
 * @see IChangePasswordUseCase The self-service counterpart.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IResetUserPasswordUseCase
{
    /**
     * Replaces the password of the user the command names.
     *
     * @param  ResetUserPasswordCommand  $command  Carries the caller, the id and
     *                                             the new password.
     * @return Result<null> Void on success, 404 when the user does not exist;
     *                      422 when the new password is too weak, a 403 or 500
     *                      otherwise.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function execute(ResetUserPasswordCommand $command): Result;
}
