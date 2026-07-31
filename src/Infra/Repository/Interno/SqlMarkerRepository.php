<?php

/**
 * SQL Marker Repository.
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
use Atlas\Query\Update;
use Domain\Models\IMarker;
use Ds\Map;
use Infra\Database\IPdoTransaction;
use Infra\Logging\ILogger;
use Infra\Repository\IMarkerGroupRepository;
use Infra\Repository\IMarkerRepository;
use PDO;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;
use Throwable;

/**
 * {@see IMarkerRepository} over the `markers` table.
 *
 * The table is `ENGINE=MEMORY`, which shapes both operations:
 *
 *  - reads filter `expires_at > NOW()` instead of deleting what they find
 *    expired, so a read never takes the table-level write lock;
 *  - {@see set()} sweeps expired rows before writing, because it is already
 *    taking that lock — and a MariaDB event does the same hourly, for the case
 *    where nothing is written at all.
 *
 * Non-transactional storage, despite joining the caller's transaction: a
 * `ROLLBACK` will not undo a marker write. That is acceptable here and nowhere
 * else — the worst case is a token marked consumed by a request that then
 * failed, which logs someone out early rather than letting a consumed token
 * live on.
 *
 * Both operations resolve the group slug through {@see IMarkerGroupRepository}
 * before touching the table, so an unregistered group is a 404 and never a row
 * filed under an index nothing could interpret.
 *
 * @see IMarkerRepository The contract this implements.
 * @uses ILogger Records failures under the `sql-marker-repository` channel.
 * @uses IPdoTransaction Supplies the caller's connection.
 * @uses IMarkerGroupRepository Turns the group slug into the stored index.
 * @see docs/adr/0003-engine-memory-for-runtime-tables.md What the MEMORY engine costs here.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class SqlMarkerRepository implements IMarkerRepository
{
    /**
     * @var string The `ENGINE=MEMORY` table both operations address.
     */
    private const string TABLE = 'markers';

    /**
     * @var ILogger Channelled copy, so these lines are attributable to this
     *              repository rather than to the request at large.
     */
    private ILogger $logger;

    /**
     * @param  ILogger  $logger  Rebound to this repository's channel; the
     *                           injected instance is not kept.
     * @param  IPdoTransaction  $session  The caller's transaction, asked for its
     *                                    connection on every call rather than
     *                                    held, since it changes per request.
     * @param  IMarkerGroupRepository  $groups  Consulted first on both
     *                                          operations, to resolve the slug
     *                                          into the index the row stores.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        ILogger $logger,
        private IPdoTransaction $session,
        private IMarkerGroupRepository $groups,
    ) {
        $this->logger = $logger->withChannel('sql-marker-repository');
    }

    /**
     * Sweeps expired rows, then writes the flag — updating the marker if it is
     * already there, inserting it otherwise.
     *
     * Update-then-insert-on-zero-rows rather than an upsert, because the
     * expiry is recomputed from `$ttlSeconds` on every write and both branches
     * have to set it.
     *
     * The expiry is stamped from PHP's clock, unlike the reads, which compare
     * against the database's `NOW()`.
     *
     * @param  IMarker  $marker  Carries the group slug, the digest and the flag.
     * @param  int  $ttlSeconds  Counted from now; applied on every write, so
     *                           consuming a marker also resets how long the
     *                           evidence survives.
     * @return Result<null> Void on success; a 404 failure when the group is not
     *                      registered, a 500 when a statement threw.
     *
     * @copyright 2026 Tachyon
     */
    public function set(IMarker $marker, int $ttlSeconds): Result
    {
        $group = $this->groups->getBySlug($marker->group);
        if ($group === null) {
            return $this->unknownGroup($marker->group);
        }

        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) {
            return Result::failure($result->getErrorId());
        }

        /** @var PDO $pdo */
        $pdo = $result->getValue();

        $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);

        try {
            // Sweep first: the lock is already ours for the write below, so the
            // reclaim is free, and it keeps the table from growing without bound
            // between the hourly event's runs.
            Delete::new($pdo)
                ->from(self::TABLE)
                ->where('expires_at <= NOW()')
                ->perform();

            $updated = Update::new($pdo)
                ->table(self::TABLE)
                ->columns([
                    'flag' => $marker->flag ? 1 : 0,
                    'expires_at' => $expiresAt,
                ])
                ->where('group_id = ', $group->id)
                ->where('hash_key = ', $marker->key)
                ->perform();

            if ($updated->rowCount() === 0) {
                Insert::new($pdo)
                    ->into(self::TABLE)
                    ->columns([
                        'group_id' => $group->id,
                        'hash_key' => $marker->key,
                        'flag' => $marker->flag ? 1 : 0,
                        'expires_at' => $expiresAt,
                    ])
                    ->perform();
            }
        } catch (Throwable $e) {
            return $this->fail('write the marker', $marker->group, $e);
        }

        return Result::void();
    }

    /**
     * Reads the flag, filtering out anything already expired.
     *
     * The `expires_at > NOW()` predicate is what lets this take no write lock:
     * an expired row is simply not selected, and is left for the next
     * {@see set()} to sweep.
     *
     * @param  string  $group  Slug of a registered group.
     * @param  string  $key  The digest.
     * @return Result<bool|null> The flag, or a successful null when no live row
     *                           matched; a 404 failure when the group is not
     *                           registered, a 500 when the select threw.
     *
     * @copyright 2026 Tachyon
     */
    public function get(string $group, string $key): Result
    {
        $registered = $this->groups->getBySlug($group);
        if ($registered === null) {
            return $this->unknownGroup($group);
        }

        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) {
            return Result::failure($result->getErrorId());
        }

        /** @var PDO $pdo */
        $pdo = $result->getValue();

        try {
            $row = Select::new($pdo)
                ->columns('flag')
                ->from(self::TABLE)
                ->where('group_id = ', $registered->id)
                ->where('hash_key = ', $key)
                // An expired marker is not a marker. Filtering here (rather than
                // deleting) is what keeps reads off the write lock.
                ->where('expires_at > NOW()')
                ->fetchOne();
        } catch (Throwable $e) {
            return $this->fail('read the marker', $group, $e);
        }

        if (!is_array($row)) {
            return Result::success(null);
        }

        return Result::success((bool) ($row['flag'] ?? false));
    }

    /**
     * The 404 both operations return when the slug is not in the group
     * registry.
     *
     * Logged at error rather than info: a slug reaching here unregistered means
     * a feature declared a marker it never registered, which is a wiring bug
     * and not a client's mistake.
     *
     * @param  string  $group  The slug that was not found.
     * @return Result<never> Always a 404 failure.
     *
     * @copyright 2026 Tachyon
     */
    private function unknownGroup(string $group): Result
    {
        $context = new LeafContext(
            message: 'Marker group is not registered.',
            details: new Map(['group' => $group]),
            code: 404,
        );
        $this->logger->error($context->message, ($context->details?->toArray() ?? []));

        return Result::failure(Leaf::newError($context));
    }

    /**
     * Logs a failed statement and turns it into the 500 both operations return.
     *
     * @param  string  $action  What was being attempted, phrased to follow
     *                          "An error occurred while trying to".
     * @param  string  $group  The slug in play, for the log line.
     * @param  Throwable  $e  What the statement threw.
     * @return Result<never> Always a 500 failure.
     *
     * @copyright 2026 Tachyon
     */
    private function fail(string $action, string $group, Throwable $e): Result
    {
        $context = new LeafContext(
            message: 'An error occurred while trying to '.$action,
            details: new Map(['group' => $group, 'error' => $e->getMessage()]),
            code: 500,
        );
        $this->logger->error($context->message, ($context->details?->toArray() ?? []));

        return Result::failure(Leaf::newError($context));
    }
}
