<?php

namespace Infra\Entity;

use Domain\Models\IUser;

class UserEntity implements IUser
{
    public function __construct(
        public int $id {
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

    public static function unserialize(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            name: $row['name'],
            email: $row['email'],
            passwordHash: $row['password_hash'],
            roles: [],
        );
    }

    public function serialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'password_hash' => $this->passwordHash,
        ];
    }
}