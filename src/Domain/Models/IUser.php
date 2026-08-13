<?php

/**
 * User Contract.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

namespace Domain\Models;

/**
 * Someone who can sign in, holding the roles that decide what they may do.
 *
 * Permissions are never held directly — a user has roles, and a role carries
 * permission slugs. That indirection is what lets a permission be granted or
 * revoked for everyone in a role at once.
 *
 * @see \Domain\TableModules\IUserTM Builds these, and owns the password policy.
 * @see IRole What actually carries the permissions.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IUser
{
    /**
     * @var string Application-generated Snowflake, Base62-encoded at the edge.
     */
    public string $id {
        get;
    }

    /**
     * @var string Display name.
     */
    public string $name {
        get;
    }

    /**
     * @var string The sign-in identity. Unique across all users; the database
     *             enforces it as well as the domain.
     */
    public string $email {
        get;
    }

    /**
     * The argon2id digest. The plaintext password exists only inside the table
     * module, long enough to be validated and hashed — nothing else in the
     * system ever holds it.
     *
     * @var string Argon2id hash, never the password.
     */
    public string $passwordHash {
        get;
    }

    /**
     * @var list<IRole> Roles held, in assignment order. Their permission slugs
     *                  are what the authorization guard checks against.
     */
    public array $roles {
        get;
    }
}
