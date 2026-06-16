<?php

namespace Domain\Models\Internal;

use Domain\Enums\Permissions;
use Domain\Models\IRole;

class Role implements IRole
{
    public function __construct(
        public int $id {
            get {
                return $this->id;
            }
        },
        public string $name {
            get {
                return $this->name;
            }
        },
        /** @var Permissions[] Array of permission IDs */
        public array $permissions {
            get {
                return $this->permissions;
            }
        },
    ) {
    }

}