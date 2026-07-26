<?php

declare(strict_types=1);

namespace Infra\Query\Interno;

use Domain\Enums\RiskClass;
use Domain\ID\Base62;
use Atlas\Statement\Select;
use Infra\Query\Cursor;
use Infra\Query\IDQL;
use Infra\Query\Product\ProductListView;
use Infra\Query\Product\ProductViewItem;
use Infra\Query\SqlQuery;
use Ds\Seq;
use Infra\Text\SearchKey;

/**
 * Keyset-paginated product listing. Owns the cursor: it decodes the incoming
 * token against the current (limit, search), paginates by `id > last_id`, and
 * mints the next cursor from the last row of a full page.
 *
 * @implements IDQL<ProductListView>
 */
final readonly class ListProductsDQL implements IDQL
{
    private const int DEFAULT_LIMIT = 20;
    private const string TABLE = 'products';

    public function __construct(
        private ?string $cursor = null,
        private ?int $limit = null,
        private ?string $search = null,
    ) {
    }

    public function toSql(): SqlQuery
    {
        $limit = $this->effectiveLimit();
        $search = $this->normalizedSearch();
        $decoded = Cursor::decode($this->cursor, $this->filters());
        $lastId = $decoded !== null ? $decoded->lastId : 0;

        $select = Select::new('mysql');

        // The `_total` sub-select must repeat the search filter to count the
        // filtered set, so its placeholder is bound inline into the expression.
        $totalSelect = Select::new('mysql')
            ->columns('COUNT(*)')
            ->from(self::TABLE);
        if ($search !== null) {
            $totalSelect->where('search_name LIKE '.$select->bindInline('%'.$search.'%'));
        }

        $select->columns('p.*', '('.$totalSelect->getQueryString().') AS _total')
            ->from(self::TABLE.' p')
            ->where('p.id > ', $lastId)
            ->orderBy('p.id ASC')
            ->limit($limit);

        if ($search !== null) {
            $select->where('p.search_name LIKE ', '%'.$search.'%');
        }

        return new SqlQuery($select->getQueryString(), $select->getBindValueArrays());
    }

    public function hydrate(array $rows): ProductListView
    {
        $limit = $this->effectiveLimit();

        /** @var Seq<ProductViewItem> $items */
        $items = new Seq();
        $total = 0;
        $lastId = 0;

        foreach ($rows as $row) {
            $id = is_numeric($row['id'] ?? null) ? (int) $row['id'] : 0;
            $name = is_scalar($row['name'] ?? null) ? (string) $row['name'] : '';
            $density = is_numeric($row['density'] ?? null) ? (float) $row['density'] : 0.0;
            $riskSlug = is_scalar($row['risk_class'] ?? null) ? (string) $row['risk_class'] : '';

            $items->push(new ProductViewItem(
                id: Base62::encode($id),
                name: $name,
                density: $density,
                riskClass: RiskClass::tryFrom($riskSlug) ?? RiskClass::Class1Explosives,
            ));

            $lastId = $id;
            $total = is_numeric($row['_total'] ?? null) ? (int) $row['_total'] : $total;
        }

        $nextCursor = $items->count() === $limit && $lastId > 0
            ? (new Cursor($lastId, $this->filters()))->encode()
            : null;

        return new ProductListView($items, $nextCursor, $total);
    }

    private function effectiveLimit(): int
    {
        return $this->limit !== null && $this->limit > 0 ? $this->limit : self::DEFAULT_LIMIT;
    }

    private function normalizedSearch(): ?string
    {
        if ($this->search === null || trim($this->search) === '') {
            return null;
        }

        return SearchKey::of($this->search);
    }

    /**
     * The parameters this page's cursor is bound to — a change invalidates it.
     *
     * @return array<string, scalar|null>
     */
    private function filters(): array
    {
        return ['limit' => $this->effectiveLimit(), 'search' => $this->normalizedSearch()];
    }
}
