<?php

namespace Infra\Entity;

use Domain\ID\Base62;
use Domain\Models\IRole;
use Infra\Text\SearchKey;

class RoleEntity implements IRole
{
    /**
     * @param  list<string>  $permissions  Permission slugs.
     */
    public function __construct(
        public string $id {
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

    /**
     * @param  array<string, mixed>  $row
     */
    public static function unserialize(array $row): self
    {
        $rawPermissions = $row['permissions'] ?? '[]';
        $decoded = is_string($rawPermissions) ? json_decode($rawPermissions, true) : [];
        $slugs = is_array($decoded) ? $decoded : [];

        // Slugs are the source of truth: an unknown one is kept as-is rather
        // than dropped, so removing a permission from the code never silently
        // strips it from the roles already stored.
        $permissions = array_values(array_filter($slugs, 'is_string'));

        $id   = $row['id'] ?? 0;
        $name = $row['name'] ?? '';

        return new self(
            id: Base62::encode(is_numeric($id) ? (int) $id : 0),
            name: is_scalar($name) ? (string) $name : '',
            permissions: $permissions,
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
            'permissions' => json_encode($this->permissions),
            'search_name' => SearchKey::of($this->name),
        ];
    }
}