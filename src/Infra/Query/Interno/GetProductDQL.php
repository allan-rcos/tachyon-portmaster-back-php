<?php

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
 * @implements IDQL<ProductViewItem|null>
 */
final readonly class GetProductDQL implements IDQL
{
    public function __construct(
        private string $id,
    ) {
    }

    public function toSql(): SqlQuery
    {
        $select = Select::new('mysql')
            ->columns('*')
            ->from('products')
            ->where('id = ', Base62::decode($this->id))
            ->limit(1);

        return new SqlQuery($select->getQueryString(), $select->getBindValueArrays());
    }

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
