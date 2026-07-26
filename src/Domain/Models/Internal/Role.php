<?php

namespace Domain\Models\Internal;

use Domain\Models\IRole;

class Role implements IRole
{
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