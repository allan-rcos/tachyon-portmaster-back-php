<?php

/**
 * List Roles Query.
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
 * Follows the keyset shape documented on {@see ListProductsDQL} — same cursor
 * handling, same repeated-filter total, same "mint a cursor only on a full page"
 * rule. What is particular here is the correlated count: it is recomputed per
 * row on every listing rather than kept on the role, so removing a user from a
 * role needs nothing updated here.
 *
 * @see RoleListView What it hydrates to.
 * @see RoleRowMapper What maps each row.
 * @see ListProductsDQL The keyset shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @implements IDQL<RoleListView>
 *
 * @internal
 */
final readonly class ListRolesDQL implements IDQL
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
     * @param  string|null  $search  Free text to match role names against; null
     *                               or blank means no filter.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private ?string $cursor = null,
        private ?int $limit = null,
        private ?string $search = null,
    ) {
    }

    /**
     * Compiles the page, its per-row user count and its total into one
     * statement.
     *
     * @return SqlQuery The statement and its bindings.
     *
     * @copyright 2026 Tachyon
     */
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

    /**
     * Builds the page through {@see RoleRowMapper}, reading the total off any
     * row and the next cursor off the last.
     *
     * @param  list<array<string, mixed>>  $rows  The page, each row carrying its
     *                                            `user_count` and the repeated
     *                                            `_total`.
     * @return RoleListView The page; empty, with no cursor and a zero total,
     *                      when nothing matched.
     *
     * @copyright 2026 Tachyon
     */
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
     * The search term reduced to the form the `search_name` column stores.
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
     * The parameters this page's cursor is bound to — a change invalidates it.
     *
     * @return array<string, scalar|null> Compared whole against what an incoming
     *                                    cursor was minted with.
     *
     * @copyright 2026 Tachyon
     */
    private function filters(): array
    {
        return ['limit' => $this->effectiveLimit(), 'search' => $this->normalizedSearch()];
    }
}
