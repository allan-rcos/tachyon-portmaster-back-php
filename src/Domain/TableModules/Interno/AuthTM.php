<?php

/**
 * Auth Table Module.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

namespace Domain\TableModules\Interno;

use Domain\Models\IUser;
use Domain\TableModules\IAuthTM;
use Domain\Security\ISecureHasher;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

/**
 * Checks a password against a user's stored hash.
 *
 * @see IAuthTM The contract.
 * @see ISecureHasher Argon2id — salted, so {@see login()} is the only way to
 *      match a password.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
readonly final class AuthTM implements IAuthTM
{
    /**
     * @param  ISecureHasher  $passwordHasher  The same hasher {@see UserTM} used
     *                                         to produce the stored digest.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private ISecureHasher $passwordHasher,
    ) {
    }

    /**
     * Validates a plaintext password against the user's stored hash.
     *
     * The message names neither the e-mail nor the password as the problem. That
     * is deliberate and the session story asserts it: a login endpoint that
     * distinguishes the two becomes a way to enumerate accounts.
     *
     * @param  IUser  $user  The user whose credentials are being checked.
     * @param  string  $password  The plaintext password.
     * @return Result<null> Void on a match; a 401 failure otherwise.
     *
     * @copyright 2026 Tachyon
     */
    public function login(IUser $user, string $password): Result
    {
        if (!$this->passwordHasher->verify($password, $user->passwordHash)) {
            return Result::failure(Leaf::newError(new LeafContext(
                message: 'Invalid e-mail or password.',
                code: 401,
            )));
        }

        return Result::void();
    }
}
