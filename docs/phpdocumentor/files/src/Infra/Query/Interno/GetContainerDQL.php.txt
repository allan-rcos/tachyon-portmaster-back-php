<?php

/**
 * Get Container Query.
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
use Infra\Query\Container\ContainerRowMapper;
use Infra\Query\Container\ContainerViewItem;
use Infra\Query\IDQL;
use Atlas\Statement\Select;
use Infra\Query\SqlQuery;

/**
 * Single-container read, without its manifest. Hydrates to null when the id has
 * no row, so the use case can surface a 404.
 *
 * The container alone. A caller that also needs what it carries goes through
 * {@see ListContainerSummariesDQL}, filtered to one id.
 *
 * @see ContainerViewItem What it hydrates to.
 * @see ContainerRowMapper What maps the row.
 * @see GetProductDQL The same single-read shape, documented there.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @implements IDQL<ContainerViewItem|null>
 *
 * @internal
 */
final readonly class GetContainerDQL implements IDQL
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
     * Compiles the lookup, decoding the Base62 id to match the column.
     *
     * @return SqlQuery The statement and its single binding.
     *
     * @copyright 2026 Tachyon
     */
    public function toSql(): SqlQuery
    {
        $select = Select::new('mysql')
            ->columns('*')
            ->from('containers')
            ->where('id = ', Base62::decode($this->id))
            ->limit(1);

        return new SqlQuery($select->getQueryString(), $select->getBindValueArrays());
    }

    /**
     * Hands the single row to {@see ContainerRowMapper}, or returns null when
     * there was none.
     *
     * @param  list<array<string, mixed>>  $rows  At most one row.
     * @return ?ContainerViewItem The container, or null when the id matched
     *                            nothing.
     *
     * @copyright 2026 Tachyon
     */
    public function hydrate(array $rows): ?ContainerViewItem
    {
        $row = $rows[0] ?? null;
        if ($row === null) {
            return null;
        }

        return ContainerRowMapper::item($row);
    }
}
