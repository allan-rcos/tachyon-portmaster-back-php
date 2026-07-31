<?php

/**
 * List Containers Query.
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
 * Follows the keyset shape documented on {@see ListProductsDQL}, with three
 * filters instead of one. Every status the caller names is resolved through
 * {@see ContainerStatus} before reaching SQL, so an unrecognised one is dropped
 * rather than matched — `status_in` with nothing recognisable in it filters on
 * nothing at all.
 *
 * `status` and `status_in` are not exclusive: both applied together narrow to
 * their intersection.
 *
 * @see ContainerListView What it hydrates to.
 * @see ContainerRowMapper What maps each row.
 * @see ListProductsDQL The keyset shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @implements IDQL<ContainerListView>
 *
 * @internal
 */
final readonly class ListContainersDQL implements IDQL
{
    /**
     * @var int Page size when the caller named none, or named one that was not
     *          positive.
     */
    private const int DEFAULT_LIMIT = 20;

    /**
     * @param  string|null  $cursor  Token from a previous page, or null to start
     *                               at the beginning.
     * @param  int|null  $limit  Page size; null or non-positive falls back to
     *                           {@see DEFAULT_LIMIT}.
     * @param  string|null  $search  Free text to match container codes against;
     *                               null or blank means no filter.
     * @param  string|null  $status  A single status slug; anything the enum does
     *                               not recognise is treated as no filter.
     * @param  string|null  $statusIn  Comma-separated status slugs;
     *                                 unrecognised ones are dropped
     *                                 individually.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private ?string $cursor = null,
        private ?int $limit = null,
        private ?string $search = null,
        private ?string $status = null,
        private ?string $statusIn = null,
    ) {
    }

    /**
     * Compiles the page and its total into one statement, applying whichever
     * filters survived normalisation to both.
     *
     * Each filter value appears twice — once on the page, once inside the
     * total's sub-select — and is bound separately each time, the second through
     * `bindInline()`, so the count is always of the same set the page draws
     * from.
     *
     * @return SqlQuery The statement and its bindings.
     *
     * @copyright 2026 Tachyon
     */
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

    /**
     * Builds the page through {@see ContainerRowMapper}, reading the total off
     * any row and the next cursor off the last.
     *
     * @param  list<array<string, mixed>>  $rows  The page, each row carrying the
     *                                            repeated `_total`.
     * @return ContainerListView The page; empty, with no cursor and a zero
     *                           total, when nothing matched.
     *
     * @copyright 2026 Tachyon
     */
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

    /**
     * The page size actually used, needed by both compilation and hydration.
     *
     * @return int The caller's limit when positive, {@see DEFAULT_LIMIT}
     *             otherwise.
     *
     * @copyright 2026 Tachyon
     */
    private function effectiveLimit(): int
    {
        return $this->limit !== null && $this->limit > 0 ? $this->limit : self::DEFAULT_LIMIT;
    }

    /**
     * The search term reduced to the form the `search_code` column stores.
     *
     * @return ?string The normalised term, or null for no filter.
     *
     * @copyright 2026 Tachyon
     */
    private function normalizedSearch(): ?string
    {
        if ($this->search === null || trim($this->search) === '') {
            return null;
        }

        return SearchKey::of($this->search);
    }

    /**
     * The single status filter, checked against the enum.
     *
     * @return ?string The slug when it names a real status; null both when none
     *                 was given and when what was given is not a status, so an
     *                 invalid filter widens the result rather than emptying it.
     *
     * @copyright 2026 Tachyon
     */
    private function normalizedStatus(): ?string
    {
        if ($this->status === null) {
            return null;
        }

        return ContainerStatus::tryFrom($this->status)?->value;
    }

    /**
     * The multi-status filter, split on commas and checked entry by entry.
     *
     * Unrecognised entries are dropped individually rather than invalidating the
     * whole filter, and an empty result means no filter at all.
     *
     * @return list<string> The recognised slugs, in the order given.
     *
     * @copyright 2026 Tachyon
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
     * The parameters this page's cursor is bound to — a change to any of them
     * invalidates it.
     *
     * The normalised forms go in, not the raw ones, so a filter that was going
     * to be dropped anyway does not by itself invalidate a cursor.
     *
     * @return array<string, scalar|null> Compared whole against what an incoming
     *                                    cursor was minted with; `status_in` is
     *                                    flattened back to a comma-joined string
     *                                    so the whole map stays scalar.
     *
     * @copyright 2026 Tachyon
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
