<?php

/**
 * User.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

namespace Domain\Models\Internal;

use Domain\Models\IRole;
use Domain\Models\IUser;

/**
 * Concrete {@see IUser}. Built only by {@see \Domain\TableModules\IUserTM},
 * which validates the profile and hashes the password before this exists.
 *
 * @see IUser What each property means.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
class User implements IUser
{
    /**
     * @param  string  $id  Application-generated Snowflake.
     * @param  string  $name  Display name.
     * @param  string  $email  Sign-in identity; unique across all users.
     * @param  string  $passwordHash  Argon2id digest — never the password.
     * @param  list<IRole>  $roles  Roles held, in assignment order.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public string $id {
            get {
                return $this->id;
            }
        },
        public string $name {
            get {
                return $this->name;
            }
        },
        public string $email {
            get {
                return $this->email;
            }
        },
        public string $passwordHash {
            get {
                return $this->passwordHash;
            }
        },
        /** @var list<IRole> */
        public array $roles {
            get {
                return $this->roles;
            }
        }
    ) {
    }

}
