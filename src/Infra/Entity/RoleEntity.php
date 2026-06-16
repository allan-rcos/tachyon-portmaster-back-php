<?php

namespace Infra\Entity;

use Domain\Enums\Permissions;
use Domain\Models\IRole;

class RoleEntity implements IRole
{
    public function __construct(
        public int $id {
            get => $this->id;
        },
        public string $name {
            get => $this->name;
        },
        public array $permissions {
            get => $this->permissions;
        }
    ) {
    }

    public static function map(IRole $role): self
    {
        return new self(
            id: $role->id,
            name: $role->name,
            permissions: $role->permissions,
        );
    }

    public static function unserialize(array $row): self
    {
        $permissions = json_decode($row['permissions'], true) ?? [];
        return new self(
            id: (int) $row['id'],
            name: $row['name'],
            permissions: array_map(fn($id) => Permissions::tryFrom($id),
                $permissions),
        );
    }

    public function serialize(): array
    {
        $permissions = array_map(
            fn($permission) => $permission->value, $this->permissions);
        return [
            'id' => $this->id,
            'name' => $this->name,
            'permissions' => json_encode($permissions),
        ];
    }
}