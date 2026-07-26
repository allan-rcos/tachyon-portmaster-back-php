<?php

namespace Domain\Models\Internal;

use Domain\Models\IRole;
use Domain\Models\IUser;

class User implements IUser
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