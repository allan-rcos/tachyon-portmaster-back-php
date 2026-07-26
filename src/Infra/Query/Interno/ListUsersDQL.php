<?php

declare(strict_types=1);

namespace Infra\Query\Interno;

use Domain\ID\Base62;
use Infra\Query\Account\AccountView;
use Atlas\Statement\Select;
use Infra\Query\IDQL;
use Infra\Query\Role\RoleRowMapper;
use Infra\Query\Role\RoleViewItem;
use Infra\Query\SqlQuery;
use Infra\Query\User\UserListView;

/**
 * Offset-paginated user listing with each user's roles joined in. One row per
 * (user, role); hydration groups the rows back into one {@see AccountView} per
 * user. `GET /users` is the only offset (page/limit) endpoint.
 *
 * @implements IDQL<UserListView>
 */
final readonly class ListUsersDQL implements IDQL
{
    private const int DEFAULT_LIMIT = 20;

    public function __construct(
        private ?int $page = null,
        private ?int $limit = null,
    ) {
    }

    public function toSql(): SqlQuery
    {
        $limit = $this->effectiveLimit();
        $page = $this->page !== null && $this->page > 0 ? $this->page : 1;
        $offset = ($page - 1) * $limit;

        // The page is taken in a derived table so the LEFT JOIN fan-out over
        // roles cannot cut the page short. `limit`/`offset` used to be
        // interpolated straight into this string; Atlas emits them as part of
        // its own LIMIT clause instead.
        // Atlas renders LIMIT/OFFSET as integer literals (not placeholders), so
        // this derived table carries no bindings and can be spliced in as a
        // string. `from(Statement)` would inline it without parentheses and
        // `as()` aliases the outer SELECT, not the source — hence the explicit
        // parenthesised alias here.
        $page = Select::new('mysql')
            ->columns('id', 'name', 'email')
            ->from('users')
            ->orderBy('id ASC')
            ->limit($limit)
            ->offset($offset);

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
            ->from('('.$page->getQueryString().') AS u')
            ->join('LEFT', 'user_roles ur', 'ur.user_id = u.id')
            ->join('LEFT', 'roles r', 'r.id = ur.role_id')
            ->orderBy('u.id ASC', 'r.id ASC');

        return new SqlQuery($select->getQueryString(), $select->getBindValueArrays());
    }

    public function hydrate(array $rows): UserListView
    {
        /** @var array<int, array{name: string, email: string, roles: list<RoleViewItem>}> $byUser */
        $byUser = [];
        /** @var list<int> $order */
        $order = [];

        foreach ($rows as $row) {
            $userId = is_numeric($row['user_id'] ?? null) ? (int) $row['user_id'] : 0;

            if (!isset($byUser[$userId])) {
                $byUser[$userId] = [
                    'name' => is_scalar($row['user_name'] ?? null) ? (string) $row['user_name'] : '',
                    'email' => is_scalar($row['user_email'] ?? null) ? (string) $row['user_email'] : '',
                    'roles' => [],
                ];
                $order[] = $userId;
            }

            if (isset($row['role_id'])) {
                $byUser[$userId]['roles'][] = RoleRowMapper::item([
                    'id' => $row['role_id'],
                    'name' => $row['role_name'] ?? '',
                    'user_count' => $row['role_user_count'] ?? 0,
                    'permissions' => $row['role_permissions'] ?? null,
                ]);
            }
        }

        $items = [];
        foreach ($order as $userId) {
            $items[] = new AccountView(
                id: Base62::encode($userId),
                name: $byUser[$userId]['name'],
                email: $byUser[$userId]['email'],
                roles: $byUser[$userId]['roles'],
            );
        }

        return new UserListView($items);
    }

    private function effectiveLimit(): int
    {
        return $this->limit !== null && $this->limit > 0 ? $this->limit : self::DEFAULT_LIMIT;
    }
}
