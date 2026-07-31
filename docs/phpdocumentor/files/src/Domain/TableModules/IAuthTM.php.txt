<?php

/**
 * Auth Table Module Contract.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

namespace Domain\TableModules;

use Domain\Models\IUser;
use Shared\Exceptions\Result;

/**
 * The credential check.
 *
 * Separate from {@see IUserTM}, which owns everything about a user *except*
 * whether a given password opens it. Splitting them keeps the password policy
 * and the password check from sharing state they do not need.
 *
 * @see \Domain\TableModules\Interno\AuthTM The implementation.
 * @see IUserTM Owns the policy the password had to satisfy to be set.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IAuthTM
{
    /**
     * Validates a plaintext password against the user's stored hash.
     *
     * @param  IUser  $user  The user whose credentials are being checked.
     * @param  string  $password  The plaintext password.
     * @return Result<null> Void when the password matches; a 401 failure
     *                      otherwise, worded so it cannot distinguish a wrong
     *                      password from an unknown account.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function login(IUser $user, string $password): Result;
}
