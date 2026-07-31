<?php

/**
 * User Entity.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

namespace Infra\Entity;

use Domain\ID\Base62;
use Domain\Models\IRole;
use Domain\Models\IUser;

/**
 * Persistence view of a user. The `users` row only — their roles live in the
 * `user_roles` pivot and are neither read nor written here.
 *
 * Follows the entity shape documented on {@see ProductEntity}, with one thing
 * worth knowing: `$roles` is part of the domain model but not part of the row,
 * so {@see unserialize()} always produces a user with an empty role list. A
 * caller that needs their roles loads them separately through
 * {@see \Infra\Repository\IRoleRepository::findByUserId()}. This is why the
 * authorization path never relies on a user's own `$roles`.
 *
 * There is no derived search column here; a user is looked up by id or by
 * email, never by a folded name.
 *
 * @see IUser The contract it satisfies.
 * @see ProductEntity The shape this follows.
 * @see \Infra\Repository\Interno\SqlUserRepository What maps through it.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
class UserEntity implements IUser
{
    /**
     * @param  string  $id  Base62, as the model carries it.
     * @param  string  $name  Display name.
     * @param  string  $email  As stored.
     * @param  string  $passwordHash  Already digested; this layer never hashes.
     * @param  list<IRole>  $roles  Their roles, if the caller has them; empty
     *                              from {@see unserialize()}, and never written.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public string $id {
            get => $this->id;
        },
        public string $name {
            get => $this->name;
        },
        public string $email {
            get => $this->email;
        },
        public string $passwordHash {
            get => $this->passwordHash;
        },
        public array $roles {
            get => $this->roles;
        }
    ) {
    }

    /**
     * Adopts any {@see IUser} into this entity so it can be serialised.
     *
     * Carries the roles across, even though {@see serialize()} will not write
     * them — the copy stays a faithful one.
     *
     * @param  IUser  $user  Whatever the domain built.
     * @return self A copy, ready to write.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function map(IUser $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            passwordHash: $user->passwordHash,
            roles: $user->roles,
        );
    }

    /**
     * Builds a user from a stored row, encoding the id back to Base62.
     *
     * Roles come back empty regardless of the row, because the row has none —
     * see the class docblock.
     *
     * @param  array<string, mixed>  $row  As the driver returned it.
     * @return self The user as the domain expects it, holding no roles.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function unserialize(array $row): self
    {
        $id           = $row['id'] ?? 0;
        $name         = $row['name'] ?? '';
        $email        = $row['email'] ?? '';
        $passwordHash = $row['password_hash'] ?? '';

        return new self(
            id: Base62::encode(is_numeric($id) ? (int) $id : 0),
            name: is_scalar($name) ? (string) $name : '',
            email: is_scalar($email) ? (string) $email : '',
            passwordHash: is_scalar($passwordHash) ? (string) $passwordHash : '',
            roles: [],
        );
    }

    /**
     * Produces the row to write.
     *
     * Roles are left out: writing them is
     * {@see \Infra\Repository\IUserRepository::syncRoles()}'s job, and including
     * them here would give two ways to change the same pivot.
     *
     * @return array<string, mixed> Column names to values, the id decoded back
     *                              to its integer form.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function serialize(): array
    {
        return [
            'id' => Base62::decode($this->id),
            'name' => $this->name,
            'email' => $this->email,
            'password_hash' => $this->passwordHash,
        ];
    }
}