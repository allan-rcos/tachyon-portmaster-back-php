<?php

declare(strict_types=1);

namespace Infra\Query\Interno;

use Domain\ID\Base62;
use Infra\Query\Account\AccountView;
use Infra\Query\IDQL;
use Infra\Query\Role\RoleRowMapper;
use Infra\Query\Role\RoleViewItem;
use Atlas\Statement\Select;
use Infra\Query\SqlQuery;

/**
 * Loads a user's profile with their roles (permissions + per-role user_count) in
 * one join. One row per role; a user with no roles still yields one row (role
 * columns null). Hydrates to null when the user id has no row at all.
 *
 * @implements IDQL<AccountView|null>
 */
final readonly class GetAccountDQL implements IDQL
{
    public function __construct(
        private string $userId,
    ) {
    }

    public function toSql(): SqlQuery
    {
        $roleUserCount = Select::new('mysql')
            ->columns('COUNT(*)')
            ->from('user_roles urc')
            ->where('urc.role_id = r.id')
            ->getQueryString();

        $select = Select::new('mysql')
            ->columns(
                'u.id AS user_id',
                'u.name AS user_name',
                'u.email AS user_email',
                'r.id AS role_id',
                'r.name AS role_name',
                'r.permissions AS role_permissions',
                '('.$roleUserCount.') AS role_user_count',
            )
            ->from('users u')
            ->join('LEFT', 'user_roles ur', 'ur.user_id = u.id')
            ->join('LEFT', 'roles r', 'r.id = ur.role_id')
            ->where('u.id = ', Base62::decode($this->userId))
            ->orderBy('r.id ASC');

        return new SqlQuery($select->getQueryString(), $select->getBindValueArrays());
    }

    public function hydrate(array $rows): ?AccountView
    {
        $first = $rows[0] ?? null;
        if ($first === null) {
            return null;
        }

        $roles = [];
        foreach ($rows as $row) {
            if (!isset($row['role_id'])) {
                continue;
            }

            $roles[] = RoleRowMapper::item([
                'id' => $row['role_id'],
                'name' => $row['role_name'] ?? '',
                'user_count' => $row['role_user_count'] ?? 0,
                'permissions' => $row['role_permissions'] ?? null,
            ]);
        }

        return new AccountView(
            id: Base62::encode(is_numeric($first['user_id'] ?? null) ? (int) $first['user_id'] : 0),
            name: is_scalar($first['user_name'] ?? null) ? (string) $first['user_name'] : '',
            email: is_scalar($first['user_email'] ?? null) ? (string) $first['user_email'] : '',
            roles: $roles,
        );
    }
}
