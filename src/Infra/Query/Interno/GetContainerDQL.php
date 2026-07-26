<?php

declare(strict_types=1);

namespace Infra\Query\Interno;

use Domain\ID\Base62;
use Infra\Query\Container\ContainerRowMapper;
use Infra\Query\Container\ContainerViewItem;
use Infra\Query\IDQL;
use Atlas\Statement\Select;
use Infra\Query\SqlQuery;

/**
 * @implements IDQL<ContainerViewItem|null>
 */
final readonly class GetContainerDQL implements IDQL
{
    public function __construct(
        private string $id,
    ) {
    }

    public function toSql(): SqlQuery
    {
        $select = Select::new('mysql')
            ->columns('*')
            ->from('containers')
            ->where('id = ', Base62::decode($this->id))
            ->limit(1);

        return new SqlQuery($select->getQueryString(), $select->getBindValueArrays());
    }

    public function hydrate(array $rows): ?ContainerViewItem
    {
        $row = $rows[0] ?? null;
        if ($row === null) {
            return null;
        }

        return ContainerRowMapper::item($row);
    }
}
