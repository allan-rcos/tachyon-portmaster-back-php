<?php

declare(strict_types=1);

namespace Infra\Query\Role;

use Domain\ID\Base62;

/**
 * Maps a `roles` row (with the DQL's `user_count` aggregate and JSON
 * `permissions` column) into a {@see RoleViewItem}. Shared by the role DQLs.
 */
final class RoleRowMapper
{
    /**
     * @param  array<string, mixed>  $row
     */
    public static function item(array $row): RoleViewItem
    {
        $id = is_numeric($row['id'] ?? null) ? (int) $row['id'] : 0;
        $name = is_scalar($row['name'] ?? null) ? (string) $row['name'] : '';
        $userCount = is_numeric($row['user_count'] ?? null) ? (int) $row['user_count'] : 0;

        return new RoleViewItem(
            id: Base62::encode($id),
            name: $name,
            userCount: $userCount,
            permissions: self::permissions($row['permissions'] ?? null),
        );
    }

    /**
     * The stored permission slugs. Unrecognised ones are kept rather than
     * dropped — the registry is a runtime catalogue, not a validator of history.
     *
     * @return list<string>
     */
    private static function permissions(mixed $raw): array
    {
        $decoded = is_string($raw) ? json_decode($raw, true) : [];
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, 'is_string'));
    }
}
