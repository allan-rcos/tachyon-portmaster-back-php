<?php

declare(strict_types=1);

namespace Infra\Query\Interno;

use Atlas\Statement\Select;
use Domain\ID\Base62;
use Infra\Query\Container\CargoItemView;
use Infra\Query\Container\ContainerRowMapper;
use Infra\Query\Container\ContainerSummaryListView;
use Infra\Query\Container\ContainerSummaryViewItem;
use Infra\Query\Container\TelemetryLogView;
use Infra\Query\Cursor;
use Infra\Query\IDQL;
use Infra\Query\SqlQuery;
use Ds\Seq;

/**
 * Keyset-paginated container summaries: each container plus its manifest (cargo
 * items with product names) and its most recent telemetry logs. Both nested
 * collections are aggregated into JSON per container (MySQL JSON_ARRAYAGG), so
 * the whole page is one row-per-container query with no cartesian fan-out.
 *
 * @implements IDQL<ContainerSummaryListView>
 */
final readonly class ListContainerSummariesDQL implements IDQL
{
    private const int DEFAULT_LIMIT = 20;
    private const int RECENT_LOGS = 10;

    public function __construct(
        private ?string $id = null,
        private ?string $cursor = null,
        private ?int $limit = null,
    ) {
    }

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
     * @return list<CargoItemView>
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
     * @return list<TelemetryLogView>
     */
    private function logs(mixed $json): array
    {
        $logs = [];
        foreach ($this->decode($json) as $entry) {
            $eventSlug = is_scalar($entry['event'] ?? null) ? (string) $entry['event'] : '';
            $logs[] = new TelemetryLogView(
                id: Base62::encode(is_numeric($entry['id'] ?? null) ? (int) $entry['id'] : 0),
                event: $eventSlug,
                description: is_scalar($entry['description'] ?? null) ? (string) $entry['description'] : null,
                timestamp: is_scalar($entry['timestamp'] ?? null) ? (string) $entry['timestamp'] : null,
            );
        }

        return $logs;
    }

    /**
     * @return list<array<string, mixed>>
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

    private function effectiveLimit(): int
    {
        return $this->limit !== null && $this->limit > 0 ? $this->limit : self::DEFAULT_LIMIT;
    }

    /**
     * @return array<string, scalar|null>
     */
    private function filters(): array
    {
        return ['limit' => $this->effectiveLimit(), 'id' => $this->id];
    }
}
