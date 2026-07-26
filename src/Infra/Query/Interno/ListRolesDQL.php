<?php

declare(strict_types=1);

namespace Infra\Query\Interno;

use Atlas\Statement\Select;
use Infra\Query\Cursor;
use Infra\Query\IDQL;
use Infra\Query\Role\RoleListView;
use Infra\Query\Role\RoleRowMapper;
use Infra\Query\Role\RoleViewItem;
use Infra\Query\SqlQuery;
use Ds\Seq;
use Infra\Text\SearchKey;

/**
 * Keyset-paginated role listing. `user_count` is a per-row correlated COUNT over
 * `user_roles`, so no denormalized column is required.
 *
 * @implements IDQL<RoleListView>
 */
final readonly class ListRolesDQL implements IDQL
{
    private const int DEFAULT_LIMIT = 20;

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

        $totalSelect = Select::new('mysql')
            ->columns('COUNT(*)')
            ->from('roles');
        if ($search !== null) {
            $totalSelect->where('search_name LIKE '.$select->bindInline('%'.$search.'%'));
        }

        $userCountSelect = Select::new('mysql')
            ->columns('COUNT(*)')
            ->from('user_roles ur')
            ->where('ur.role_id = r.id');

        $select->columns(
            'r.*',
            '('.$userCountSelect->getQueryString().') AS user_count',
            '('.$totalSelect->getQueryString().') AS _total',
        )
            ->from('roles r')
            ->where('r.id > ', $lastId)
            ->orderBy('r.id ASC')
            ->limit($limit);

        if ($search !== null) {
            $select->where('r.search_name LIKE ', '%'.$search.'%');
        }

        return new SqlQuery($select->getQueryString(), $select->getBindValueArrays());
    }

    public function hydrate(array $rows): RoleListView
    {
        $limit = $this->effectiveLimit();

        /** @var Seq<RoleViewItem> $items */
        $items = new Seq();
        $total = 0;
        $lastId = 0;

        foreach ($rows as $row) {
            $item = RoleRowMapper::item($row);
            $items->push($item);
            $lastId = is_numeric($row['id'] ?? null) ? (int) $row['id'] : $lastId;
            $total = is_numeric($row['_total'] ?? null) ? (int) $row['_total'] : $total;
        }

        $nextCursor = $items->count() === $limit && $lastId > 0
            ? (new Cursor($lastId, $this->filters()))->encode()
            : null;

        return new RoleListView($items, $nextCursor, $total);
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
     * @return array<string, scalar|null>
     */
    private function filters(): array
    {
        return ['limit' => $this->effectiveLimit(), 'search' => $this->normalizedSearch()];
    }
}
