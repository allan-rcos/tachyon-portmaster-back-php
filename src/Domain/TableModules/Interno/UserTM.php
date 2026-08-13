<?php

/**
 * User Table Module.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

namespace Domain\TableModules\Interno;

use Domain\Models\Internal\User;
use Domain\Models\IRole;
use Domain\Models\IUser;
use Domain\Security\ISecureHasher;
use Domain\ID\IDatabaseIdGenerator;
use Domain\TableModules\IUserTM;
use Ds\Map;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

/**
 * Builds validated users, and is the only place a password is hashed.
 *
 * Validation is split in two because the two halves are needed independently:
 * {@see validateProfile()} runs on create and update, {@see validatePassword()}
 * on create and both password changes. Create runs both and reports their
 * failures together.
 *
 * {@see changePassword()} and {@see resetPassword()} both delegate to
 * {@see withPassword()} — identical here, since the policy does not care who is
 * asking. What differs is what the calling use case demands first.
 *
 * @see IUserTM The contract.
 * @see ISecureHasher Argon2id; salted, so the digest is not reproducible.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
readonly final class UserTM implements IUserTM
{
    /**
     * @var int Matches the `VARCHAR(255)` the name column is declared as.
     */
    private const int MAX_NAME_LENGTH = 255;

    /**
     * @var int Matches the `VARCHAR(255)` the email column is declared as.
     */
    private const int MAX_EMAIL_LENGTH = 255;

    /**
     * @param  IDatabaseIdGenerator  $idGenerator  Snowflake generator — a user id
     *                                             becomes a primary key.
     * @param  ISecureHasher  $passwordHasher  The same hasher {@see AuthTM}
     *                                         verifies against.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IDatabaseIdGenerator $idGenerator,
        private ISecureHasher $passwordHasher,
    ) {
    }

    /**
     * Builds a new user, assigning an id and hashing the password.
     *
     * Profile and password failures are collected into one 422 rather than
     * returning at the first, so a client filling a registration form sees
     * everything wrong with it at once.
     *
     * @param  string  $name  Display name; required, at most 255 characters.
     * @param  string  $email  Sign-in identity; required, well-formed, at most
     *                         255 characters.
     * @param  string  $password  Plaintext; hashed here and never retained.
     * @param  list<IRole>  $roles  Roles to hold; may be empty.
     * @return Result<IUser> A 422 failure listing every field that broke a rule.
     *
     * @copyright 2026 Tachyon
     */
    public function create(
        string $name,
        string $email,
        string $password,
        array $roles,
    ): Result {
        $errors = $this->validateProfile($name, $email);
        $this->validatePassword($password, $errors);

        if (!$errors->isEmpty()) {
            return Result::failure(Leaf::newError(new LeafContext('Validation errors', $errors, 422)));
        }

        return Result::success(new User(
            id: $this->idGenerator->generate(),
            name: $name,
            email: $email,
            passwordHash: $this->passwordHasher->hash($password),
            roles: $roles,
        ));
    }

    /**
     * Produces the user with updated profile data.
     *
     * The existing hash and roles are carried across untouched — this method
     * cannot change either, by construction.
     *
     * @param  IUser  $user  Current state.
     * @param  string  $name  New display name.
     * @param  string  $email  New sign-in identity.
     * @return Result<IUser> A 422 failure listing every field that broke a rule.
     *
     * @copyright 2026 Tachyon
     */
    public function update(IUser $user, string $name, string $email): Result
    {
        $errors = $this->validateProfile($name, $email);
        if (!$errors->isEmpty()) {
            return Result::failure(Leaf::newError(new LeafContext('Validation errors', $errors, 422)));
        }

        return Result::success(new User(
            id: $user->id,
            name: $name,
            email: $email,
            passwordHash: $user->passwordHash,
            roles: $user->roles,
        ));
    }

    /**
     * Produces the user with a new password — the self-service change.
     *
     * @param  IUser  $user  Current state.
     * @param  string  $newPassword  Plaintext; hashed here.
     * @return Result<IUser> A 422 failure on a weak password.
     *
     * @copyright 2026 Tachyon
     */
    public function changePassword(IUser $user, string $newPassword): Result
    {
        return $this->withPassword($user, $newPassword);
    }

    /**
     * Produces the user with a new password — the administrator reset.
     *
     * @param  IUser  $user  Current state.
     * @param  string  $newPassword  Plaintext; hashed here.
     * @return Result<IUser> A 422 failure on a weak password.
     *
     * @copyright 2026 Tachyon
     */
    public function resetPassword(IUser $user, string $newPassword): Result
    {
        return $this->withPassword($user, $newPassword);
    }

    /**
     * Validates and hashes a new password onto a copy of the user.
     *
     * Shared by both password entry points, which is what guarantees the policy
     * cannot apply to one and not the other.
     *
     * @param  IUser  $user  Current state.
     * @param  string  $newPassword  Plaintext.
     * @return Result<IUser> A 422 failure on a weak password.
     *
     * @copyright 2026 Tachyon
     */
    private function withPassword(IUser $user, string $newPassword): Result
    {
        /** @var Map<string, string> $errors */
        $errors = new Map();
        $this->validatePassword($newPassword, $errors);

        if (!$errors->isEmpty()) {
            return Result::failure(Leaf::newError(new LeafContext('Validation errors', $errors, 422)));
        }

        return Result::success(new User(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            passwordHash: $this->passwordHasher->hash($newPassword),
            roles: $user->roles,
        ));
    }

    /**
     * Every rule the name and e-mail must satisfy.
     *
     * @param  string  $name  Display name.
     * @param  string  $email  Sign-in identity.
     * @return Map<string, string> Field name to message; empty when valid.
     *
     * @copyright 2026 Tachyon
     */
    private function validateProfile(string $name, string $email): Map
    {
        /** @var Map<string, string> $errors */
        $errors = new Map();

        if (empty($name)) {
            $errors->put('name', 'Name is required');
        } elseif (strlen($name) > self::MAX_NAME_LENGTH) {
            $errors->put('name', 'Name must not exceed ' . self::MAX_NAME_LENGTH . ' characters');
        }

        if (empty($email)) {
            $errors->put('email', 'Email is required');
        } elseif (strlen($email) > self::MAX_EMAIL_LENGTH) {
            $errors->put('email', 'Email must not exceed ' . self::MAX_EMAIL_LENGTH . ' characters');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors->put('email', 'Invalid email format');
        }

        return $errors;
    }

    /**
     * The password strength policy: at least 8 characters, with a lowercase
     * letter, an uppercase letter and a digit.
     *
     * Takes the error map by reference and adds to it, rather than returning its
     * own, so {@see create()} can report a weak password alongside a malformed
     * e-mail instead of one after the other.
     *
     * @param  string  $password  Plaintext to check.
     * @param  Map<string, string>  $errors  Collector; mutated in place.
     * @return void
     *
     * @copyright 2026 Tachyon
     */
    private function validatePassword(string $password, Map $errors): void
    {
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
            $errors->put(
                'password',
                'Password must be at least 8 characters long and include uppercase, lowercase letters, and numbers',
            );
        }
    }
}
