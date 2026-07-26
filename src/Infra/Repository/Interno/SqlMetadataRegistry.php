<?php

declare(strict_types=1);

namespace Infra\Repository\Interno;

use Atlas\Query\Insert;
use Atlas\Query\Select;
use Ds\Map;
use Ds\Seq;
use Infra\Database\Pool\IPDOPool;
use Infra\Logging\ILogger;
use PDO;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;
use Throwable;

/**
 * Shared machinery for the **system metadata** registries
 * ({@see PermissionRegistry}, {@see TelemetryEventRegistry}), stored in a
 * `ENGINE=MEMORY` table.
 *
 * It used to be an {@see \OpenSwoole\Table} per registry, which was wrong for a
 * reason worth recording: the object graph is built inside `WorkerStart`, i.e.
 * *after* the fork, so each of the workers allocated its own table. Metadata got
 * away with it — every worker re-registers the same entries from the same code,
 * so the copies agree — but nothing that is written at runtime could. Moving to
 * the database makes one shared catalogue out of all of them, and lets markers
 * ({@see SqlMarkerRepository}) reuse the same machinery.
 *
 * The table is still RAM, so the cost of the move is a round-trip, not a disk
 * write.
 *
 * **Connection.** These registries take the {@see IPDOPool} directly rather than
 * a transaction session: registration runs at boot, outside any request, so
 * there is no boundary open for them to enlist in — the same reason
 * {@see \Infra\Query\Interno\SqlQueryRepository} leases its own connection.
 *
 * Registration is idempotent by slug, so four workers declaring the same
 * permission collapse to one row instead of fighting over an index.
 *
 * @template TItem of object
 */
abstract class SqlMetadataRegistry
{
    protected readonly ILogger $logger;

    public function __construct(
        private readonly IPDOPool $pool,
        ILogger $logger,
    ) {
        $this->logger = $logger->withChannel($this->label().'-registry');
    }

    /**
     * Rebuilds the concrete metadata object from a stored row.
     *
     * @return TItem
     */
    abstract protected function hydrate(string $slug, int $id): object;

    /** Names the metadata family in error messages ("permission", "telemetry event"). */
    abstract protected function label(): string;

    /** The `ENGINE=MEMORY` table backing this family. */
    abstract protected function table(): string;

    /**
     * Registers an entry and returns it with its registry index. Idempotent by
     * slug: a known entry is returned untouched, never re-inserted.
     *
     * Idempotence is read-then-insert rather than an upsert because there is
     * nothing an upsert could update: a slug is the entire entry, so a row that
     * already exists is already correct.
     *
     * The read-then-insert *is* racy across the four workers booting at once,
     * and the unique index is what settles it: whoever loses gets a duplicate-key
     * error, re-reads, and finds the row the winner just wrote. That is the
     * intended path, not an error path.
     *
     * @return Result<TItem> Failure 500 when the write fails for any other reason.
     */
    protected function register(string $slug): Result
    {
        $existing = $this->find($slug);
        if ($existing !== null) {
            return Result::success($existing);
        }

        $lease = $this->pool->get();
        if (!$lease->isSuccess()) {
            return Result::failure($lease->getErrorId());
        }

        /** @var PDO $pdo */
        $pdo = $lease->getValue();
        $failure = null;

        try {
            Insert::new($pdo)
                ->into($this->table())
                ->columns(['slug' => $slug])
                ->perform();
        } catch (Throwable $e) {
            $failure = $e;
        } finally {
            $this->pool->put($pdo);
        }

        $registered = $this->find($slug);

        if ($registered !== null) {
            // Either our insert landed, or another worker's did while we raced
            // it. Both mean the catalogue holds what the caller asked for.
            return Result::success($registered);
        }

        if ($failure !== null) {
            return $this->fail('register the '.$this->label(), $slug, $failure);
        }

        $context = new LeafContext(
            message: 'The '.$this->label().' vanished right after being registered',
            details: new Map(['slug' => $slug]),
            code: 500,
        );
        $this->logger->error($context->message, ($context->details?->toArray() ?? []));

        return Result::failure(Leaf::newError($context));
    }

    /**
     * @return TItem|null
     */
    protected function find(string $slug): ?object
    {
        return $this->fetchOne('slug = ', $slug);
    }

    /**
     * @return TItem|null
     */
    protected function findById(int $id): ?object
    {
        return $this->fetchOne('id = ', $id);
    }

    /**
     * Every entry, in registration order — the index doubles as the sort key.
     *
     * @return Seq<TItem>
     */
    protected function listAll(): Seq
    {
        /** @var Seq<TItem> $items */
        $items = new Seq();

        $lease = $this->pool->get();
        if (!$lease->isSuccess()) {
            return $items;
        }

        /** @var PDO $pdo */
        $pdo = $lease->getValue();

        try {
            /** @var list<array<string, mixed>> $rows */
            $rows = Select::new($pdo)
                ->columns('id', 'slug')
                ->from($this->table())
                ->orderBy('id ASC')
                ->fetchAll();

            foreach ($rows as $row) {
                $items->push($this->hydrateRow($row));
            }
        } catch (Throwable $e) {
            $this->fail('list the '.$this->label().'s', '*', $e);
        } finally {
            $this->pool->put($pdo);
        }

        return $items;
    }

    public function has(string $slug): bool
    {
        return $this->find($slug) !== null;
    }

    /**
     * @return TItem|null
     */
    private function fetchOne(string $condition, int|string $value): ?object
    {
        $lease = $this->pool->get();
        if (!$lease->isSuccess()) {
            return null;
        }

        /** @var PDO $pdo */
        $pdo = $lease->getValue();

        try {
            $row = Select::new($pdo)
                ->columns('id', 'slug')
                ->from($this->table())
                ->where($condition, $value)
                ->fetchOne();

            if (!is_array($row)) {
                return null;
            }

            /** @var array<string, mixed> $row */
            return $this->hydrateRow($row);
        } catch (Throwable $e) {
            $this->fail('load the '.$this->label(), (string) $value, $e);

            return null;
        } finally {
            $this->pool->put($pdo);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return TItem
     */
    private function hydrateRow(array $row): object
    {
        return $this->hydrate(
            is_scalar($row['slug'] ?? null) ? (string) $row['slug'] : '',
            is_numeric($row['id'] ?? null) ? (int) $row['id'] : 0,
        );
    }

    /**
     * @return Result<never>
     */
    private function fail(string $action, string $slug, Throwable $e): Result
    {
        $context = new LeafContext(
            message: 'An error occurred while trying to '.$action,
            details: new Map(['slug' => $slug, 'error' => $e->getMessage()]),
            code: 500,
        );
        $this->logger->error($context->message, ($context->details?->toArray() ?? []));

        return Result::failure(Leaf::newError($context));
    }
}
