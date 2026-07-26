<?php

declare(strict_types=1);

namespace Infra\Query\Interno;

use Atlas\Statement\Select;
use Domain\Enums\ContainerStatus;
use Infra\Query\Container\ContainerListView;
use Infra\Query\Container\ContainerRowMapper;
use Infra\Query\Container\ContainerViewItem;
use Infra\Query\Cursor;
use Infra\Query\IDQL;
use Infra\Query\SqlQuery;
use Ds\Seq;
use Infra\Text\SearchKey;

/**
 * Keyset-paginated container listing with optional `search` (code), single
 * `status` and multi `status_in` (csv) filters. All filters are part of the
 * cursor identity, so changing any of them restarts pagination.
 *
 * @implements IDQL<ContainerListView>
 */
final readonly class ListContainersDQL implements IDQL
{
    private const int DEFAULT_LIMIT = 20;

    public function __construct(
        private ?string $cursor = null,
        private ?int $limit = null,
        private ?string $search = null,
        private ?string $status = null,
        private ?string $statusIn = null,
    ) {
    }

    public function toSql(): SqlQuery
    {
        $limit = $this->effectiveLimit();
        $search = $this->normalizedSearch();
        $status = $this->normalizedStatus();
        $statusIn = $this->normalizedStatusIn();
        $decoded = Cursor::decode($this->cursor, $this->filters());
        $lastId = $decoded !== null ? $decoded->lastId : 0;

        $select = Select::new('mysql');

        // The `_total` sub-select repeats the same filters so it counts the
        // filtered set; each value is spliced in through bindInline() — never
        // interpolated — so the count matches the page.
        $totalSelect = Select::new('mysql')
            ->columns('COUNT(*)')
            ->from('containers');
        if ($search !== null) {
            $totalSelect->where('search_code LIKE '.$select->bindInline('%'.$search.'%'));
        }
        if ($status !== null) {
            $totalSelect->where('status = '.$select->bindInline($status));
        }
        if ($statusIn !== []) {
            $inline = array_map(
                static fn (string $slug): string => $select->bindInline($slug),
                $statusIn,
            );
            $totalSelect->where('status IN ('.implode(', ', $inline).')');
        }

        $select->columns('c.*', '('.$totalSelect->getQueryString().') AS _total')
            ->from('containers c')
            ->where('c.id > ', $lastId)
            ->orderBy('c.id ASC')
            ->limit($limit);

        if ($search !== null) {
            $select->where('c.search_code LIKE ', '%'.$search.'%');
        }
        if ($status !== null) {
            $select->where('c.status = ', $status);
        }
        if ($statusIn !== []) {
            $select->where('c.status IN ', $statusIn);
        }

        return new SqlQuery($select->getQueryString(), $select->getBindValueArrays());
    }

    public function hydrate(array $rows): ContainerListView
    {
        $limit = $this->effectiveLimit();

        /** @var Seq<ContainerViewItem> $items */
        $items = new Seq();
        $total = 0;
        $lastId = 0;

        foreach ($rows as $row) {
            $item = ContainerRowMapper::item($row);
            $items->push($item);
            $lastId = is_numeric($row['id'] ?? null) ? (int) $row['id'] : $lastId;
            $total = is_numeric($row['_total'] ?? null) ? (int) $row['_total'] : $total;
        }

        $nextCursor = $items->count() === $limit && $lastId > 0
            ? (new Cursor($lastId, $this->filters()))->encode()
            : null;

        return new ContainerListView($items, $nextCursor, $total);
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

    private function normalizedStatus(): ?string
    {
        if ($this->status === null) {
            return null;
        }

        return ContainerStatus::tryFrom($this->status)?->value;
    }

    /**
     * @return list<string>
     */
    private function normalizedStatusIn(): array
    {
        if ($this->statusIn === null || trim($this->statusIn) === '') {
            return [];
        }

        $slugs = [];
        foreach (explode(',', $this->statusIn) as $raw) {
            $status = ContainerStatus::tryFrom(trim($raw));
            if ($status !== null) {
                $slugs[] = $status->value;
            }
        }

        return $slugs;
    }

    /**
     * @return array<string, scalar|null>
     */
    private function filters(): array
    {
        return [
            'limit' => $this->effectiveLimit(),
            'search' => $this->normalizedSearch(),
            'status' => $this->normalizedStatus(),
            'status_in' => implode(',', $this->normalizedStatusIn()),
        ];
    }
}
