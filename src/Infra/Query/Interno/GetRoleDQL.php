<?php

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
 * @implements IDQL<RoleViewItem|null>
 */
final readonly class GetRoleDQL implements IDQL
{
    public function __construct(
        private string $id,
    ) {
    }

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

    public function hydrate(array $rows): ?RoleViewItem
    {
        $row = $rows[0] ?? null;
        if ($row === null) {
            return null;
        }

        return RoleRowMapper::item($row);
    }
}
