<?php

/**
 * Get Product Query.
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

use Domain\Enums\RiskClass;
use Domain\ID\Base62;
use Infra\Query\IDQL;
use Infra\Query\Product\ProductViewItem;
use Atlas\Statement\Select;
use Infra\Query\SqlQuery;

/**
 * Single-product read. Hydrates to null when the id has no row, so the use case
 * can surface a 404.
 *
 * The null is the point: {@see \Infra\Query\IQueryRepository} treats an empty
 * result set as a success, so a query that must distinguish "not found" from
 * "the read failed" says so in its view type rather than in its failure.
 *
 * @see ProductViewItem What it hydrates to.
 * @see ListProductsDQL The paged sibling.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @implements IDQL<ProductViewItem|null>
 *
 * @internal
 */
final readonly class GetProductDQL implements IDQL
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
            ->from('products')
            ->where('id = ', Base62::decode($this->id))
            ->limit(1);

        return new SqlQuery($select->getQueryString(), $select->getBindValueArrays());
    }

    /**
     * The id this lookup is for.
     *
     * Answered like every other DQL, though nothing caches a read by id today:
     * a `SELECT` on the primary key costs about what the cache lookup would, so
     * there is no round trip to save. See
     * {@see \Infra\Repository\IViewCacheRepository}.
     *
     * @return string The query's identity.
     *
     * @copyright 2026 Tachyon
     */
    public function cacheKey(): string
    {
        return 'get_product:'.$this->id;
    }

    /**
     * Builds the item from the single row, or null when there was none.
     *
     * Each field is coerced rather than trusted; an unrecognised risk class
     * degrades to {@see RiskClass::Class1Explosives} rather than failing the
     * read.
     *
     * @param  list<array<string, mixed>>  $rows  At most one row.
     * @return ?ProductViewItem The product, or null when the id matched nothing.
     *
     * @copyright 2026 Tachyon
     */
    public function hydrate(array $rows): ?ProductViewItem
    {
        $row = $rows[0] ?? null;
        if ($row === null) {
            return null;
        }

        $id = is_numeric($row['id'] ?? null) ? (int) $row['id'] : 0;
        $name = is_scalar($row['name'] ?? null) ? (string) $row['name'] : '';
        $density = is_numeric($row['density'] ?? null) ? (float) $row['density'] : 0.0;
        $riskSlug = is_scalar($row['risk_class'] ?? null) ? (string) $row['risk_class'] : '';

        return new ProductViewItem(
            id: Base62::encode($id),
            name: $name,
            density: $density,
            riskClass: RiskClass::tryFrom($riskSlug) ?? RiskClass::Class1Explosives,
        );
    }
}
