<?php

/**
 * Update User Use Case Contract.
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

use App\Commands\User\UpdateUserCommand;
use Domain\Models\IUser;
use Shared\Exceptions\Result;

/**
 * Changes another user's profile.
 *
 * Follows the update shape documented on {@see IUpdateProductUseCase}. Profile
 * fields only — roles are {@see IUpdateUserRolesUseCase}'s, the password
 * {@see IResetUserPasswordUseCase}'s.
 *
 * Guarded by `user:update`. The self-service counterpart,
 * {@see IUpdateAccountUseCase}, needs no permission at all.
 *
 * @see UpdateUserCommand What it takes.
 * @see \App\Services\Interno\UpdateUserUseCase The implementation.
 * @see IUpdateProductUseCase The shape.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IUpdateUserUseCase
{
    /**
     * Replaces the profile of the user the command names.
     *
     * @param  UpdateUserCommand  $command  Carries the caller, the id and the
     *                                      profile fields.
     * @return Result<IUser> The updated user, or 404 when not found; 422 or 409
     *                       when the address is refused or taken, a 403 or 500
     *                       otherwise.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function execute(UpdateUserCommand $command): Result;
}
