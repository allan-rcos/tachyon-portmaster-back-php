<?php

/**
 * Role.
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

/**
 * Concrete {@see IRole}. Built only by {@see \Domain\TableModules\IRoleTM},
 * which validates the permission slugs first.
 *
 * @see IRole What each property means.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
class Role implements IRole
{
    /**
     * @param  string  $id  Application-generated Snowflake.
     * @param  string  $name  Display name.
     * @param  list<string>  $permissions  Permission slugs granted by this role.
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
        /** @var list<string> Permission slugs granted by this role */
        public array $permissions {
            get {
                return $this->permissions;
            }
        },
    ) {
    }

}
