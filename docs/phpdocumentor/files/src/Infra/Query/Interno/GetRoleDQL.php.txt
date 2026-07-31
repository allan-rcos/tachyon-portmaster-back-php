<?php

/**
 * Get Role Query.
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
use Infra\Query\IDQL;
use Infra\Query\Role\RoleRowMapper;
use Infra\Query\Role\RoleViewItem;
use Atlas\Statement\Select;
use Infra\Query\SqlQuery;

/**
 * Single-role read (also used to build write responses that must include the
 * fresh `user_count`). Hydrates to null when the id has no row.
 *
 * That second use is why it exists on the write path at all: a role's user count
 * is not on the domain model, so a create or update that must echo the role back
 * complete re-reads it through here.
 *
 * @see RoleViewItem What it hydrates to.
 * @see RoleRowMapper What maps the row.
 * @see ListRolesDQL The paged sibling.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @implements IDQL<RoleViewItem|null>
 *
 * @internal
 */
final readonly class GetRoleDQL implements IDQL
{
    /**
     * @param  string  $id  Base62 id, decoded when the statement is compiled.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private string $id,
    ) {
    }

    /**
     * Compiles the lookup together with the correlated count of who holds the
     * role.
     *
     * @return SqlQuery The statement and its single binding — the count
     *                  sub-select carries no user-supplied value.
     *
     * @copyright 2026 Tachyon
     */
    public function toSql(): SqlQuery
    {
        // The correlated COUNT is built through Atlas too — it carries no
        // user-supplied value, so there is nothing to bind in it.
        $userCount = Select::new('mysql')
            ->columns('COUNT(*)')
            ->from('user_roles ur')
            ->where('ur.role_id = r.id')
            ->getQueryString();

        $select = Select::new('mysql')
            ->columns(
                'r.*',
                '('.$userCount.') AS user_count',
            )
            ->from('roles r')
            ->where('r.id = ', Base62::decode($this->id))
            ->limit(1);

        return new SqlQuery($select->getQueryString(), $select->getBindValueArrays());
    }

    /**
     * Hands the single row to {@see RoleRowMapper}, or returns null when there
     * was none.
     *
     * @param  list<array<string, mixed>>  $rows  At most one row, carrying the
     *                                            `user_count` aggregate.
     * @return ?RoleViewItem The role, or null when the id matched nothing.
     *
     * @copyright 2026 Tachyon
     */
    public function hydrate(array $rows): ?RoleViewItem
    {
        $row = $rows[0] ?? null;
        if ($row === null) {
            return null;
        }

        return RoleRowMapper::item($row);
    }
}
