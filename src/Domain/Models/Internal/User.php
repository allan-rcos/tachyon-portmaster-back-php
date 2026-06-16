<?php

namespace Domain\Models\Internal;

use Domain\Models\IUser;

class User implements IUser
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
        /** @var Role[] */
        public array $roles {
            get {
                return $this->roles;
            }
        }
    ) {
    }

}