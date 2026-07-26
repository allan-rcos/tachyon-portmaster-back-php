<?php

namespace Infra\Entity;

use Domain\ID\Base62;
use Domain\Models\IRole;
use Domain\Models\IUser;

class UserEntity implements IUser
{
    /**
     * @param  list<IRole>  $roles
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
     * @param  array<string, mixed>  $row
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
     * @return array<string, mixed>
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