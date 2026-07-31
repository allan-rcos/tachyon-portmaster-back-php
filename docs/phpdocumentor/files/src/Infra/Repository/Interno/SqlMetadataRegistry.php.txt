<?php

/**
 * SQL Metadata Registry.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

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
 * ({@see PermissionRegistry}, {@see MarkerGroupRegistry}), stored in a
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
 * A subclass supplies three things — {@see hydrate()}, {@see label()} and
 * {@see table()} — and gets the whole read/register surface from here.
 *
 * @see PermissionRegistry A subclass, over `permissions`.
 * @see MarkerGroupRegistry A subclass, over `marker_groups`.
 * @see docs/adr/0002-metadata-registries-in-the-database.md Why this lives in the database.
 * @see docs/adr/0003-engine-memory-for-runtime-tables.md Why that table is RAM.
 *
 * @uses IPDOPool Leased per statement, since there is no request boundary to enlist in.
 * @uses ILogger Records failures under a `<family>-registry` channel.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @template TItem of object
 *
 * @internal
 */
abstract class SqlMetadataRegistry
{
    /**
     * @var ILogger Channelled from {@see label()}, so each family's lines are
     *              attributable to it.
     */
    protected readonly ILogger $logger;

    /**
     * Leases are taken per statement rather than held, so a registry idle
     * between boot and the next registration occupies no connection.
     *
     * @param  IPDOPool  $pool  Borrowed from directly; these run outside any
     *                          request, so there is no transaction session to
     *                          enlist in.
     * @param  ILogger  $logger  Rebound to this family's channel, which is why
     *                           {@see label()} must be answerable before the
     *                           subclass is fully constructed.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private readonly IPDOPool $pool,
        ILogger $logger,
    ) {
        $this->logger = $logger->withChannel($this->label().'-registry');
    }

    /**
     * Rebuilds the concrete metadata object from a stored row.
     *
     * @param  string  $slug  The stored slug.
     * @param  int  $id  The stored registry index.
     * @return TItem The subclass's own metadata type.
     *
     * @copyright 2026 Tachyon
     */
    abstract protected function hydrate(string $slug, int $id): object;

    /**
     * Names the metadata family in error messages ("permission", "marker
     * group").
     *
     * Also forms the log channel, so it is read during construction.
     *
     * @return string Singular and lower-case; the plural is formed by appending
     *                an `s`.
     *
     * @copyright 2026 Tachyon
     */
    abstract protected function label(): string;

    /**
     * The `ENGINE=MEMORY` table backing this family.
     *
     * @return string Interpolated into the SQL, so it must be a literal the
     *                subclass chose and never anything caller-supplied.
     *
     * @copyright 2026 Tachyon
     */
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
     * @param  string  $slug  The entry to register; the whole of it.
     * @return Result<TItem> The entry carrying its index. Failure 500 when the
     *                       write fails for any other reason, and also in the
     *                       one case that should be impossible — the insert
     *                       reporting success while the re-read finds nothing.
     *
     * @copyright 2026 Tachyon
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
     * The entry registered under a slug.
     *
     * @param  string  $slug  What was registered.
     * @return TItem|null Null when the slug is unknown, and also when the read
     *                    itself failed — see {@see fetchOne()} for why the two
     *                    are not distinguished.
     *
     * @copyright 2026 Tachyon
     */
    protected function find(string $slug): ?object
    {
        return $this->fetchOne('slug = ', $slug);
    }

    /**
     * The entry registered under an index.
     *
     * @param  int  $id  The index assigned at registration.
     * @return TItem|null Null when the index is unknown, or the read failed.
     *
     * @copyright 2026 Tachyon
     */
    protected function findById(int $id): ?object
    {
        return $this->fetchOne('id = ', $id);
    }

    /**
     * Every entry, in registration order — the index doubles as the sort key.
     *
     * @return Seq<TItem> The whole family; empty both when nothing is registered
     *                    and when the read failed, the latter having been
     *                    logged.
     *
     * @copyright 2026 Tachyon
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

    /**
     * Whether a slug has been registered.
     *
     * @param  string  $slug  What to look for.
     * @return bool True when the catalogue holds it.
     *
     * @copyright 2026 Tachyon
     */
    public function has(string $slug): bool
    {
        return $this->find($slug) !== null;
    }

    /**
     * The one-row read both finders share, leasing a connection and returning it
     * whatever happens.
     *
     * A failure returns null rather than a {@see Result}: these reads sit on the
     * authorization path of every request, where the callers are typed to answer
     * "registered or not" and have nowhere to put an error. The failure is
     * logged, and an unreachable database therefore reads as "nothing is
     * registered".
     *
     * @param  string  $condition  An Atlas predicate fragment ending in the
     *                             operator, chosen here and never
     *                             caller-supplied.
     * @param  int|string  $value  Bound as a parameter.
     * @return TItem|null Null when nothing matched or the select threw.
     *
     * @copyright 2026 Tachyon
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
     * Coerces a raw row into the arguments {@see hydrate()} expects.
     *
     * A missing or non-scalar column degrades to `''` and `0` rather than
     * throwing, so one malformed row cannot take down a boot-time registration
     * sweep.
     *
     * @param  array<string, mixed>  $row  As the driver returned it.
     * @return TItem The subclass's own metadata type.
     *
     * @copyright 2026 Tachyon
     */
    private function hydrateRow(array $row): object
    {
        return $this->hydrate(
            is_scalar($row['slug'] ?? null) ? (string) $row['slug'] : '',
            is_numeric($row['id'] ?? null) ? (int) $row['id'] : 0,
        );
    }

    /**
     * Logs a failed statement and builds the 500 for it.
     *
     * Callers that have nowhere to return a {@see Result} — the reads — invoke
     * this for the logging alone and discard what comes back.
     *
     * @param  string  $action  What was being attempted, phrased to follow
     *                          "An error occurred while trying to".
     * @param  string  $slug  The slug in play, or `*` for a whole-table read.
     * @param  Throwable  $e  What the statement threw.
     * @return Result<never> Always a 500 failure.
     *
     * @copyright 2026 Tachyon
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
