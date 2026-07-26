<?php

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
 */
final readonly class SqlQueryRepository implements IQueryRepository
{
    private ILogger $logger;

    public function __construct(
        private IPDOPool $pool,
        ILogger $logger,
    ) {
        $this->logger = $logger->withChannel('sql-query-repository');
    }

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
