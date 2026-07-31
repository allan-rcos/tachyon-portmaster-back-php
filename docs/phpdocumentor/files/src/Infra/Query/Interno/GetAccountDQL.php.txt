<?php

/**
 * Get Account Query.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

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
 * Left joins, not inner: a user without roles must still be found, and that is
 * exactly what distinguishes "no rows" (no such user, hydrating to null) from
 * "one row with null role columns" (a real user holding nothing).
 *
 * @see AccountView What it hydrates to.
 * @see RoleRowMapper Reused to map each role, with the joined columns renamed to
 *                    the shape it expects.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @implements IDQL<AccountView|null>
 *
 * @internal
 */
final readonly class GetAccountDQL implements IDQL
{
    /**
     * @param  string  $userId  Base62 id, decoded when the statement is
     *                          compiled.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private string $userId,
    ) {
    }

    /**
     * Compiles the profile-and-roles join, aliasing every column so the user's
     * and the role's cannot collide.
     *
     * Each role carries its own correlated count of how many users hold it,
     * counted across the whole pivot rather than within this result.
     *
     * @return SqlQuery The statement and its single binding.
     *
     * @copyright 2026 Tachyon
     */
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

    /**
     * Folds the joined rows back into one profile carrying a list of roles.
     *
     * The user's own fields are read off the first row, since the join repeats
     * them; rows whose `role_id` is null are the left join's way of saying "this
     * user holds no roles" and are skipped rather than mapped.
     *
     * @param  list<array<string, mixed>>  $rows  One row per role, or a single
     *                                            row with null role columns.
     * @return ?AccountView The profile, or null when the id matched no user.
     *
     * @copyright 2026 Tachyon
     */
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
