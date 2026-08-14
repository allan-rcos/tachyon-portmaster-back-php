<?php

/**
 * List Container Summaries Query.
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
use Domain\Enums\TelemetryEvent;
use Domain\ID\Base62;
use Infra\Query\Container\CargoItemView;
use Infra\Query\Container\ContainerRowMapper;
use Infra\Query\Container\ContainerSummaryListView;
use Infra\Query\Container\ContainerSummaryViewItem;
use Infra\Query\Container\TelemetryLogView;
use Infra\Query\Cursor;
use Infra\Query\IDQL;
use Infra\Query\SqlQuery;
use Shared\Time\Utc;
use Ds\Seq;

/**
 * Keyset-paginated container summaries: each container plus its manifest (cargo
 * items with product names) and its most recent telemetry logs. Both nested
 * collections are aggregated into JSON per container (MySQL JSON_ARRAYAGG), so
 * the whole page is one row-per-container query with no cartesian fan-out.
 *
 * Follows the keyset shape documented on {@see ListProductsDQL}, filtered by id
 * rather than by search. Passing an `$id` narrows the page to one container,
 * which is how a caller fetches a single container *with* its manifest —
 * {@see GetContainerDQL} returns the container alone.
 *
 * @see ContainerSummaryListView What it hydrates to.
 * @see ContainerRowMapper What maps the container part of each row.
 * @see ListProductsDQL The keyset shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @implements IDQL<ContainerSummaryListView>
 *
 * @internal
 */
final readonly class ListContainerSummariesDQL implements IDQL
{
    /**
     * @var int Page size when the caller named none, or named one that was not
     *          positive.
     */
    private const int DEFAULT_LIMIT = 20;

    /**
     * @var int How many telemetry entries each container carries back. A cap,
     *          not a page: there is no way to ask for the ones beyond it here.
     */
    private const int RECENT_LOGS = 10;

    /**
     * @param  string|null  $id  Base62 id to narrow to a single container, or
     *                           null for all of them.
     * @param  string|null  $cursor  Token from a previous page, or null to start
     *                               at the beginning.
     * @param  int|null  $limit  Page size; null or non-positive falls back to
     *                           {@see DEFAULT_LIMIT}.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private ?string $id = null,
        private ?string $cursor = null,
        private ?int $limit = null,
    ) {
    }

    /**
     * Compiles the page, both nested collections and the total into one
     * statement.
     *
     * Manifest and telemetry are each aggregated to JSON per container, so the
     * page stays one row per container however much either contains.
     *
     * @return SqlQuery The statement and its bindings.
     *
     * @copyright 2026 Tachyon
     */
    public function toSql(): SqlQuery
    {
        $limit = $this->effectiveLimit();
        $decoded = Cursor::decode($this->cursor, $this->filters());
        $lastId = $decoded !== null ? $decoded->lastId : 0;

        $select = Select::new('mysql');

        // Both nested collections are aggregated to JSON per container, and both
        // are built through Atlas like every other query in this layer. The
        // JSON_OBJECT projections stay literal — they are column lists, not
        // values — but the structure around them (FROM, JOIN, WHERE, ORDER BY,
        // LIMIT/OFFSET) is the builder's, so it reads as SQL the rest of the
        // layer would recognise. Neither carries a bound value: the only
        // variable is the recent-logs cap, and that is a class constant.
        $manifestSelect = Select::new('mysql')
            ->columns("JSON_ARRAYAGG(JSON_OBJECT('product_id', ci.product_id, 'product_name', p.name, 'quantity', ci.quantity, 'weight', ci.weight))")
            ->from('container_items ci')
            ->join('INNER', 'products p', 'p.id = ci.product_id')
            ->where('ci.container_id = c.id');

        $manifest = '('.$manifestSelect->getQueryString().')';

        // The recent-logs window is a correlated *scalar* subquery rather than a
        // derived table. MariaDB has no LATERAL, so a derived table may not
        // reference `c.id` from the enclosing query — the obvious
        // `FROM (SELECT ... WHERE container_id = c.id LIMIT n) t` fails with
        // "Unknown column 'c.id' in 'WHERE'". Bounding on the nth-newest id
        // gets the same rows with a correlation the engine accepts.
        $window = Select::new('mysql')
            ->columns('t2.id')
            ->from('telemetry_logs t2')
            ->where('t2.container_id = c.id')
            ->orderBy('t2.id DESC')
            ->limit(1)
            ->offset(self::RECENT_LOGS - 1);

        $logsSelect = Select::new('mysql')
            ->columns("JSON_ARRAYAGG(JSON_OBJECT('id', t.id, 'event', t.event, 'description', t.description, 'timestamp', t.timestamp))")
            ->from('telemetry_logs t')
            ->where('t.container_id = c.id')
            // COALESCE covers containers holding fewer logs than the cap, where
            // the window subquery yields NULL and every log should be returned.
            ->where('t.id >= COALESCE(('.$window->getQueryString().'), 0)');

        $logs = '('.$logsSelect->getQueryString().')';

        // The `_total` sub-select repeats the id filter through bindInline() so
        // it counts the same set the page draws from.
        $totalSelect = Select::new('mysql')
            ->columns('COUNT(*)')
            ->from('containers');
        if ($this->id !== null) {
            $totalSelect->where('id = '.$select->bindInline(Base62::decode($this->id)));
        }

        $select->columns(
            'c.*',
            $manifest.' AS manifest_json',
            $logs.' AS logs_json',
            '('.$totalSelect->getQueryString().') AS _total',
        )
            ->from('containers c')
            ->where('c.id > ', $lastId)
            ->orderBy('c.id ASC')
            ->limit($limit);

        if ($this->id !== null) {
            $select->where('c.id = ', Base62::decode($this->id));
        }

        return new SqlQuery($select->getQueryString(), $select->getBindValueArrays());
    }

    /**
     * Builds the page, decoding each row's two JSON columns into their nested
     * lists.
     *
     * @param  list<array<string, mixed>>  $rows  One row per container, each
     *                                            carrying `manifest_json`,
     *                                            `logs_json` and the repeated
     *                                            `_total`.
     * @return ContainerSummaryListView The page; empty, with no cursor and a
     *                                  zero total, when nothing matched.
     *
     * @copyright 2026 Tachyon
     */
    public function hydrate(array $rows): ContainerSummaryListView
    {
        $limit = $this->effectiveLimit();

        /** @var Seq<ContainerSummaryViewItem> $items */
        $items = new Seq();
        $total = 0;
        $lastId = 0;

        foreach ($rows as $row) {
            $container = ContainerRowMapper::item($row);
            $items->push(new ContainerSummaryViewItem(
                container: $container,
                manifest: $this->manifest($row['manifest_json'] ?? null),
                recentLogs: $this->logs($row['logs_json'] ?? null),
            ));
            $lastId = is_numeric($row['id'] ?? null) ? (int) $row['id'] : $lastId;
            $total = is_numeric($row['_total'] ?? null) ? (int) $row['_total'] : $total;
        }

        $nextCursor = $items->count() === $limit && $lastId > 0
            ? (new Cursor($lastId, $this->filters()))->encode()
            : null;

        return new ContainerSummaryListView($items, $nextCursor, $total);
    }

    /**
     * Turns one container's aggregated manifest JSON into cargo lines.
     *
     * @param  mixed  $json  The `manifest_json` column; null for a container
     *                       carrying nothing.
     * @return list<CargoItemView> The lines, empty when there are none or the
     *                             column could not be decoded.
     *
     * @copyright 2026 Tachyon
     */
    private function manifest(mixed $json): array
    {
        $items = [];
        foreach ($this->decode($json) as $entry) {
            $items[] = new CargoItemView(
                productId: Base62::encode(is_numeric($entry['product_id'] ?? null) ? (int) $entry['product_id'] : 0),
                productName: is_scalar($entry['product_name'] ?? null) ? (string) $entry['product_name'] : '',
                quantity: is_numeric($entry['quantity'] ?? null) ? (float) $entry['quantity'] : 0.0,
                weight: is_numeric($entry['weight'] ?? null) ? (float) $entry['weight'] : 0.0,
            );
        }

        return $items;
    }

    /**
     * Turns one container's aggregated telemetry JSON into log entries.
     *
     * @param  mixed  $json  The `logs_json` column; null for a container with no
     *                       history.
     * @return list<TelemetryLogView> At most {@see RECENT_LOGS} entries, in the
     *                                order the aggregation produced them.
     *
     * @copyright 2026 Tachyon
     */
    private function logs(mixed $json): array
    {
        $logs = [];
        foreach ($this->decode($json) as $entry) {
            $eventSlug = is_scalar($entry['event'] ?? null) ? (string) $entry['event'] : '';
            $event     = TelemetryEvent::tryFrom($eventSlug);

            // A stored value matching no case is dropped rather than coerced.
            // The wire field is an enum, so there is no value a client could be
            // handed that means "something happened but not one of these", and
            // picking a case would report an event that never occurred. The row
            // itself stays in telemetry_logs either way.
            if ($event === null) {
                continue;
            }

            $logs[] = new TelemetryLogView(
                id: Base62::encode(is_numeric($entry['id'] ?? null) ? (int) $entry['id'] : 0),
                event: $event,
                description: is_scalar($entry['description'] ?? null) ? (string) $entry['description'] : null,
                // With its zone: a stored DATETIME names none. See Shared\Time\Utc.
                timestamp: Utc::iso8601(
                    is_scalar($entry['timestamp'] ?? null) ? (string) $entry['timestamp'] : null,
                ),
            );
        }

        return $logs;
    }

    /**
     * Decodes an aggregated JSON column into a list of entries.
     *
     * Shared by both nested collections. Anything that is not a string, does not
     * parse, or parses to something other than an array yields an empty list —
     * so a container whose aggregate came back null reads as "carries nothing"
     * rather than failing the whole page.
     *
     * @param  mixed  $json  The column as the driver returned it.
     * @return list<array<string, mixed>> The entries, non-array members dropped.
     *
     * @copyright 2026 Tachyon
     */
    private function decode(mixed $json): array
    {
        if (!is_string($json)) {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $rows = [];
        foreach ($decoded as $entry) {
            if (is_array($entry)) {
                $rows[] = $entry;
            }
        }

        return $rows;
    }

    /**
     * The filters and the page position, which together are what this query is.
     *
     * Built from {@see filters()} rather than from the constructor arguments, so
     * a filter added to the query is in the key by construction instead of by
     * remembering to put it there.
     *
     * The position is the decoded `lastId`, not the cursor token: that is what
     * {@see toSql()} pages from, and a token minted for other filters is
     * discarded, so every token that means "start from the beginning" has to
     * land on the same key as no token at all.
     *
     * @return string The query's identity.
     *
     * @copyright 2026 Tachyon
     */
    public function cacheKey(): string
    {
        $decoded = Cursor::decode($this->cursor, $this->filters());
        $after = $decoded !== null ? $decoded->lastId : 0;

        $parts = ['after='.$after];
        foreach ($this->filters() as $name => $value) {
            $parts[] = $name.'='.($value ?? '');
        }

        return 'list_container_summaries:'.implode(';', $parts);
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
     * The parameters this page's cursor is bound to — a change invalidates it.
     *
     * @return array<string, scalar|null> Compared whole against what an incoming
     *                                    cursor was minted with.
     *
     * @copyright 2026 Tachyon
     */
    private function filters(): array
    {
        return ['limit' => $this->effectiveLimit(), 'id' => $this->id];
    }
}
