<?php

/**
 * Change Password Use Case Contract.
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

use App\Commands\Account\ChangePasswordCommand;
use Shared\Exceptions\Result;

/**
 * Changes the caller's own password.
 *
 * Follows the write shape documented on
 * {@see \App\Services\Interno\CreateProductUseCase}, with the current password
 * verified before anything is written.
 *
 * Needs no permission: the target is always the caller themselves, and holding a
 * {@see \App\Context\UserContext} already means being authenticated. What
 * guards it instead is the current password — see {@see ChangePasswordCommand}
 * for why no user id is accepted.
 *
 * @see ChangePasswordCommand What it takes.
 * @see \App\Services\Interno\ChangePasswordUseCase The implementation.
 * @see IResetUserPasswordUseCase The administrative counterpart.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IChangePasswordUseCase
{
    /**
     * Verifies the current password, then replaces it.
     *
     * @param  ChangePasswordCommand  $command  Carries the caller, their current
     *                                          password and the new one.
     * @return Result<null> Void on success; 401 when the current password is
     *                      wrong, 422 when the new one is too weak.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function execute(ChangePasswordCommand $command): Result;
}
