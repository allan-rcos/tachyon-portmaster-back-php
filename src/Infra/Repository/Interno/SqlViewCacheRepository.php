<?php

/**
 * SQL View Cache Repository.
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

use Atlas\Query\Delete;
use Atlas\Query\Insert;
use Atlas\Query\Select;
use Ds\Map;
use Infra\Config\CacheLimits;
use Infra\Database\Pool\IPDOPool;
use Infra\Logging\ILogger;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use PDO;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;
use Throwable;

/**
 * {@see IViewCacheRepository} over the `view_cache` table.
 *
 * The table is `ENGINE=MEMORY`, which is what makes an invalidation reach every
 * worker rather than only the one that handled the write. It also shapes the
 * code here, the same way it shapes {@see SqlMarkerRepository}:
 *
 *  - reads filter `expires_at > NOW()` rather than deleting what they find
 *    expired, so a read never takes the table-level write lock;
 *  - {@see put()} sweeps nothing, unlike {@see SqlMarkerRepository::set()}. A
 *    cache write happens on every miss, so scanning the table each time would
 *    cost more than the read it saves; a MariaDB event reclaims expired rows
 *    every minute instead;
 *  - the payload column is a fixed-width `VARBINARY`, because MEMORY supports
 *    neither `BLOB` nor `TEXT`. See {@see MAX_PAYLOAD_BYTES}.
 *
 * **Connection.** It takes the {@see IPDOPool} directly rather than a
 * transaction session, for the same reason {@see \Infra\Query\Interno\SqlQueryRepository}
 * and {@see SqlMetadataRegistry} do: the cache takes no part in the write
 * boundary. Invalidation deliberately runs *after* the commit it follows, so
 * there is no boundary left to enlist in — and MEMORY would not honour a
 * rollback anyway.
 *
 * **Nothing here fails a read.** Every path that cannot do its job answers
 * {@see Result::void()} and logs: a cache that is full, or slow, or holding an
 * entry written by an older deploy, must degrade into a cache miss. The one
 * thing it must never do is turn a working query into a 500.
 *
 * @see IViewCacheRepository The contract this implements.
 * @uses IPDOPool Leased per statement, since there is no request boundary to enlist in.
 * @uses ILogger Records failures under the `sql-view-cache-repository` channel.
 * @see docs/adr/0003-engine-memory-for-runtime-tables.md What the MEMORY engine costs here.
 * @see docs/adr/0010-read-cache-in-a-memory-table.md Why the cache is shaped this way.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class SqlViewCacheRepository implements IViewCacheRepository
{
    /**
     * @var string The `ENGINE=MEMORY` table every operation addresses.
     */
    private const string TABLE = 'view_cache';

    /**
     * @var int Widest payload the table can hold, in bytes.
     *
     * This is an artefact of the storage, not a policy, which is why it lives
     * here and not in {@see CacheLimits}: MEMORY supports no `BLOB`, so the
     * column is a `VARBINARY` of a declared width, and MEMORY pads every row to
     * that width whatever it holds. A Redis-backed implementation of the same
     * port would carry no such constant.
     *
     * **It must equal the column width in
     * `db/migrations/000003_view_cache.up.sql`.** A view that serialises larger
     * is not cached at all, which is also what keeps a `?limit=100000` from
     * attempting a row the column cannot hold — the request is answered from the
     * database and nothing is stored.
     */
    private const int MAX_PAYLOAD_BYTES = 16384;

    /**
     * @var int Longest key the table can hold, in bytes.
     *
     * Same reasoning as {@see MAX_PAYLOAD_BYTES}, and the same requirement to
     * match the DDL. Keys are short by construction — a listing's filters and
     * its cursor position — but a caller is free to send a search term of any
     * length, and that term is part of the query's identity and therefore part
     * of the key. Truncating it would make two different searches share an
     * entry, so an over-long key means the query is not cached instead.
     */
    private const int MAX_KEY_BYTES = 191;

    /**
     * @var ILogger Channelled copy, so these lines are attributable to the cache
     *              rather than to whichever read was passing through it.
     */
    private ILogger $logger;

    /**
     * @param  IPDOPool  $pool  Borrowed from per statement, and returned before
     *                          the method leaves.
     * @param  ILogger  $logger  Rebound to this repository's channel; the
     *                           injected instance is not kept.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IPDOPool $pool,
        ILogger $logger,
    ) {
        $this->logger = $logger->withChannel('sql-view-cache-repository');
    }

    /**
     * Reads the entry, filtering out anything already expired.
     *
     * The `expires_at > NOW()` predicate is what lets this take no write lock:
     * an expired row is simply not selected, and is left for the every-minute
     * MariaDB event to reclaim.
     *
     * Bytes that do not deserialize are a miss, not a failure. They were written
     * by an earlier deploy whose views had a different shape, and recomputing in
     * silence is what keeps that deploy from becoming an incident. `igbinary`
     * answers `null` for anything it cannot read, which is why the check is a
     * null check and not an exception handler.
     *
     * @param  ViewCacheGroup  $group  The slice to look in.
     * @param  string  $key  From the DQL about to be run.
     * @return Result<mixed> The stored view; {@see Result::void()} for a miss,
     *                       an expired entry, an unreadable one, and a lease or
     *                       statement that failed — the caller carries on to the
     *                       database in every one of those cases.
     *
     * @copyright 2026 Tachyon
     */
    public function get(ViewCacheGroup $group, string $key): Result
    {
        if (strlen($key) > self::MAX_KEY_BYTES) {
            // It was never stored; see MAX_KEY_BYTES.
            return Result::void();
        }

        $lease = $this->pool->get();
        if (!$lease->isSuccess()) {
            return Result::void();
        }

        /** @var PDO $pdo */
        $pdo = $lease->getValue();

        try {
            $select = Select::new($pdo);

            // Hex on the wire, because igbinary output is not valid UTF-8 and
            // the connection is utf8mb4. See the ADR.
            $row = $select
                ->columns('HEX(payload) AS payload_hex')
                ->from(self::TABLE)
                ->where('cache_group = ', $group->value)
                ->where('cache_key = ', $key)
                ->where('expires_at > NOW()')
                ->fetchOne();
        } catch (Throwable $e) {
            $this->warn('read the cached view', $group, $e);

            return Result::void();
        } finally {
            $this->pool->put($pdo);
        }

        if (!is_array($row) || !is_string($row['payload_hex'] ?? null)) {
            return Result::void();
        }

        $bytes = @hex2bin($row['payload_hex']);
        if ($bytes === false) {
            return Result::void();
        }

        $view = @igbinary_unserialize($bytes);

        return $view === null ? Result::void() : Result::success($view);
    }

    /**
     * Stores the view, replacing the entry when the key is already there.
     *
     * Delete then insert, since Atlas has no upsert and nothing in this layer
     * reaches past it to raw SQL. The pair is not atomic, so a concurrent reader
     * can land between the two statements and find nothing — it recomputes a
     * page that was about to be cached, which is a wasted query and never a
     * wrong answer.
     *
     * Nothing expired is swept here, unlike {@see SqlMarkerRepository::set()}: a
     * cache write happens on every miss, and scanning the table each time would
     * be the most expensive thing on the read path. The every-minute MariaDB
     * event reclaims that memory instead, and reads already filter on
     * `expires_at`, so the delay costs RAM and never correctness.
     *
     * Nothing here fails the caller. A view too large for the column, a full
     * table, a statement that threw — all answer void, because the caller
     * already holds the correct result and the only cost is that the next
     * request recomputes it.
     *
     * @param  ViewCacheGroup  $group  The slice this belongs to.
     * @param  string  $key  The key {@see get()} will be asked for.
     * @param  mixed  $view  The hydrated view, as the DQL produced it.
     * @return Result<null> Always void, including when the entry was
     *                      deliberately not stored.
     *
     * @copyright 2026 Tachyon
     */
    public function put(ViewCacheGroup $group, string $key, mixed $view): Result
    {
        if (strlen($key) > self::MAX_KEY_BYTES) {
            return Result::void();
        }

        try {
            $bytes = @igbinary_serialize($view);
        } catch (Throwable) {
            // A view that will not serialize is not a reason to fail: it is
            // already built, and the cache is an optimisation.
            return Result::void();
        }

        if (!is_string($bytes) || strlen($bytes) > self::MAX_PAYLOAD_BYTES) {
            // Too large to store is not an error; see MAX_PAYLOAD_BYTES.
            return Result::void();
        }

        $lease = $this->pool->get();
        if (!$lease->isSuccess()) {
            return Result::void();
        }

        /** @var PDO $pdo */
        $pdo = $lease->getValue();

        try {
            // Delete then insert: Atlas has no upsert.
            Delete::new($pdo)
                ->from(self::TABLE)
                ->where('cache_group = ', $group->value)
                ->where('cache_key = ', $key)
                ->perform();

            $insert = Insert::new($pdo);
            $insert
                ->into(self::TABLE)
                ->columns([
                    'cache_group' => $group->value,
                    'cache_key' => $key,
                ])
                // set() writes its expression verbatim, so the hex goes in bound
                // and UNHEX restores the bytes.
                ->set('payload', 'UNHEX('.$insert->bindInline(bin2hex($bytes)).')')
                // Computed by the database, not stamped from PHP: get() compares
                // against the same NOW(), and 30 seconds leaves no room for drift.
                ->set('expires_at', 'NOW() + INTERVAL '.CacheLimits::TTL_SECONDS.' SECOND')
                ->perform();
        } catch (Throwable $e) {
            // Error 1114 is "table is full": max_heap_table_size reached, and
            // MEMORY has no LRU. Worth logging, not worth failing the read.
            $this->warn('store the view in the cache', $group, $e);
        } finally {
            $this->pool->put($pdo);
        }

        return Result::void();
    }

    /**
     * Discards the whole group.
     *
     * No prefix scan: the group is already the slice, and comparing prefixes
     * would match `container` against a hypothetical `container_summary`. The
     * `BTREE` index on `cache_group` exists for this statement — MEMORY indexes
     * default to `HASH`, which cannot serve a lookup on the leading part of the
     * primary key.
     *
     * @param  ViewCacheGroup  $group  Everything filed under it goes.
     * @return Result<null> Void on success; a 500 failure when the statement
     *                      threw. The caller has already committed by the time
     *                      it gets here and is expected to ignore that failure —
     *                      the entries it meant to drop expire on their own.
     *
     * @copyright 2026 Tachyon
     */
    public function invalidate(ViewCacheGroup $group): Result
    {
        $lease = $this->pool->get();
        if (!$lease->isSuccess()) {
            return Result::failure($lease->getErrorId());
        }

        /** @var PDO $pdo */
        $pdo = $lease->getValue();

        try {
            Delete::new($pdo)
                ->from(self::TABLE)
                ->where('cache_group = ', $group->value)
                ->perform();
        } catch (Throwable $e) {
            return $this->fail('invalidate the cached views', $group, $e);
        } finally {
            $this->pool->put($pdo);
        }

        return Result::void();
    }

    /**
     * Records a cache operation that could not be completed, without turning it
     * into a failure.
     *
     * Warning rather than error: every caller of this carries on to the
     * database, so nothing is broken from the outside. It is here because a
     * cache that has quietly stopped storing anything looks exactly like a cache
     * that is working, and the only difference visible from outside is the load.
     *
     * @param  string  $action  What was being attempted, phrased to follow
     *                          "An error occurred while trying to".
     * @param  ViewCacheGroup  $group  The slice in play, for the log line.
     * @param  Throwable  $e  What the statement threw.
     *
     * @copyright 2026 Tachyon
     */
    private function warn(string $action, ViewCacheGroup $group, Throwable $e): void
    {
        $this->logger->warn('An error occurred while trying to '.$action, [
            'group' => $group->value,
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * Logs a failed invalidation and turns it into the 500 it reports.
     *
     * Invalidation is the one operation that does not degrade into a miss: an
     * entry that should have been dropped and was not is stale data being
     * served, so it is recorded at error level even though the caller ignores
     * the result.
     *
     * @param  string  $action  What was being attempted, phrased to follow
     *                          "An error occurred while trying to".
     * @param  ViewCacheGroup  $group  The slice in play, for the log line.
     * @param  Throwable  $e  What the statement threw.
     * @return Result<never> Always a 500 failure.
     *
     * @copyright 2026 Tachyon
     */
    private function fail(string $action, ViewCacheGroup $group, Throwable $e): Result
    {
        $context = new LeafContext(
            message: 'An error occurred while trying to '.$action,
            details: new Map(['group' => $group->value, 'error' => $e->getMessage()]),
            code: 500,
        );
        $this->logger->error($context->message, ($context->details?->toArray() ?? []));

        return Result::failure(Leaf::newError($context));
    }
}
