<?php

/**
 * Update Account Use Case Contract.
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

use App\Commands\Account\UpdateAccountCommand;
use Domain\Models\IUser;
use Shared\Exceptions\Result;

/**
 * Changes the caller's own profile.
 *
 * Follows the update shape documented on {@see IUpdateProductUseCase}, with the
 * target taken from the context rather than from the command.
 *
 * Needs no permission: the target is always the caller themselves, and holding a
 * {@see \App\Context\UserContext} already means being authenticated. Every user
 * may correct their own name; changing someone else's is
 * {@see IUpdateUserUseCase}, which is guarded.
 *
 * @see UpdateAccountCommand What it takes.
 * @see \App\Services\Interno\UpdateAccountUseCase The implementation.
 * @see IUpdateUserUseCase The administrative counterpart.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IUpdateAccountUseCase
{
    /**
     * Replaces the caller's own profile fields.
     *
     * @param  UpdateAccountCommand  $command  Carries the caller and their new
     *                                         name and address.
     * @return Result<IUser> The updated user; 422 or 409 when the address is
     *                       refused or taken, a 500 otherwise.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function execute(UpdateAccountCommand $command): Result;
}
