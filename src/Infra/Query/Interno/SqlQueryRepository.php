<?php

/**
 * SQL Query Repository.
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

use Ds\Map;
use Infra\Database\Pool\IPDOPool;
use Infra\Query\IDQL;
use Infra\Query\IQueryRepository;
use PDO;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;
use Infra\Logging\ILogger;
use Throwable;

/**
 * {@see IQueryRepository} over the raw-PDO {@see IPDOPool}.
 *
 * Reads are lightweight: it leases a connection, prepares/executes the SQL the
 * DQL compiled, then lets the DQL hydrate the rows — the repository never sees
 * the concrete view type. It runs outside the write unit-of-work (its own leased
 * connection), so read endpoints don't open a transaction.
 *
 * The lease is returned in a `finally`, so a query that throws mid-hydration
 * still gives the connection back.
 *
 * @see IQueryRepository The contract this implements.
 * @uses IPDOPool Leased per query; reads take no part in the write boundary.
 * @uses ILogger Records failures under the `sql-query-repository` channel.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class SqlQueryRepository implements IQueryRepository
{
    /**
     * @var ILogger Channelled copy, so a failing read is attributable to the
     *              runner rather than to whichever DQL it was carrying.
     */
    private ILogger $logger;

    /**
     * @param  IPDOPool  $pool  Borrowed from per query, and returned before the
     *                          method leaves.
     * @param  ILogger  $logger  Rebound to this runner's channel; the injected
     *                           instance is not kept.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IPDOPool $pool,
        ILogger $logger,
    ) {
        $this->logger = $logger->withChannel('sql-query-repository');
    }

    /**
     * Leases a connection, runs what the DQL compiled, and hands the rows back
     * to it to hydrate.
     *
     * Compilation, execution and hydration all sit inside the `try`, so a DQL
     * that throws while building its SQL or while reading a row it did not
     * expect produces the same 500 as a database that refused the statement.
     * The failing DQL's class name goes into the log line, since the statement
     * itself may carry values not worth recording.
     *
     * @param  IDQL<TView>  $dql  Compiles itself and hydrates its own view.
     * @return Result<TView> The hydrated view; a 500 failure when the lease, the
     *                       statement or the hydration failed.
     *
     * @copyright 2026 Tachyon
     *
     * @template TView
     */
    public function run(IDQL $dql): Result
    {
        $lease = $this->pool->get();
        if (!$lease->isSuccess()) {
            return Result::failure($lease->getErrorId());
        }

        /** @var PDO $pdo */
        $pdo = $lease->getValue();

        try {
            $query = $dql->toSql();

            $statement = $pdo->prepare($query->sql);

            // bindValue() rather than execute($bindings): the DQL hands over
            // Atlas's [value, PDO type] pairs, and dropping the type would send
            // every integer as a string — which MySQL rejects for LIMIT.
            foreach ($query->bindings as $name => [$value, $type]) {
                $statement->bindValue($name, $value, $type ?? PDO::PARAM_STR);
            }

            $statement->execute();

            /** @var list<array<string, mixed>> $rows */
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

            return Result::success($dql->hydrate($rows));
        } catch (Throwable $e) {
            $context = new LeafContext(
                message: 'An error occurred while running the query',
                details: new Map([
                    'dql'   => $dql::class,
                    'error' => $e->getMessage(),
                ]),
                code: 500,
            );
            $this->logger->error($context->message, ($context->details?->toArray() ?? []));

            return Result::failure(Leaf::newError($context));
        } finally {
            $this->pool->put($pdo);
        }
    }
}
