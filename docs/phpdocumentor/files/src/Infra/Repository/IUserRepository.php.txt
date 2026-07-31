<?php

/**
 * User Repository Contract.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

namespace Infra\Repository;

use Domain\Models\IUser;
use Shared\Exceptions\Result;

/**
 * Persistence for users — the write side.
 *
 * Stores what the table module already validated; it does not validate and it
 * does not hash — a user arrives here with its password already digested. Every
 * method enlists in the caller's open transaction, so a use case that rolls back
 * undoes these too.
 *
 * This contract owns the `user_roles` pivot: {@see syncRoles()} writes it, while
 * {@see IRoleRepository::findByUserId()} reads it back.
 *
 * @see IUser What is stored.
 * @see \Domain\TableModules\IUserTM Validates and hashes before anything reaches here.
 * @see \Infra\Repository\Interno\SqlUserRepository The implementation.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IUserRepository
{
    /**
     * Whether the system has any user at all.
     *
     * Exists for the bootstrap endpoint, which must open exactly once: a
     * deployment with no users cannot create one through `/users`, because that
     * needs a permission that needs a role that needs a user to grant it.
     *
     * @return Result<bool> The answer; a 500 failure when the read broke. A
     *                      failure is not a "no" — the bootstrap endpoint must
     *                      not open on a database it could not reach.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function hasAny(): Result;

    /**
     * Loads a user by id.
     *
     * Their roles do not come with it — those are loaded through
     * {@see IRoleRepository::findByUserId()}.
     *
     * @param  string  $id  Base62 id as it travels the application.
     * @return Result<IUser> A 404 failure when no row matched; a 500 when the
     *                       read itself failed, which includes an id too
     *                       malformed to decode.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function findById(string $id): Result;

    /**
     * Loads a user by email address.
     *
     * The address is lowercased before matching, so the caller does not have to
     * normalise what a login form gave it. This is the read that backs login.
     *
     * @param  string  $email  In any case; the caller need not normalise it.
     * @return Result<IUser> A 404 failure when no row matched; a 500 when the
     *                       read itself failed.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function findByEmail(string $email): Result;

    /**
     * Writes a new user.
     *
     * @param  IUser  $user  Already validated, with the password hashed.
     * @return Result<null> Void on success; a 500 failure on any write error,
     *                      which includes an email address already taken — the
     *                      unique index rejects it.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function insert(IUser $user): Result;

    /**
     * Overwrites an existing user, matched on its id.
     *
     * Role assignments are not touched; those move through {@see syncRoles()}.
     *
     * @param  IUser  $user  The new state, already validated and hashed.
     * @return Result<null> Void on success; a 500 failure on a write error.
     *                      Matching no row is *not* a failure — callers that
     *                      care load the user first.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function update(IUser $user): Result;

    /**
     * Removes a user.
     *
     * Their role assignments go with them — `user_roles` cascades on delete.
     *
     * @param  string  $id  Base62 id.
     * @return Result<null> Void on success; a 500 failure on a write error.
     *                      Matching no row is *not* a failure — the use case
     *                      loads the user first, and that is where the 404 comes
     *                      from.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function delete(string $id): Result;

    /**
     * Replaces the user's role assignments with exactly the set given.
     *
     * A full sync, not a merge: roles left out are removed and roles listed are
     * added, so passing an empty list strips the user of every role. Repeats in
     * the list collapse to one assignment.
     *
     * @param  string  $userId  Base62 id of the user.
     * @param  list<string>  $roleIds  Base62 ids of the roles they should hold
     *                                 afterwards.
     * @return Result<null> Void on success; a 500 failure on a write error,
     *                      which includes a role id that does not exist — the
     *                      foreign key rejects it, and the caller's transaction
     *                      is what undoes the removals already made.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function syncRoles(string $userId, array $roleIds): Result;
}