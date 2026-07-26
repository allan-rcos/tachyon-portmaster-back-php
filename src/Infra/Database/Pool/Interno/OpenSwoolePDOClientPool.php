<?php

declare(strict_types=1);

namespace Infra\Database\Pool\Interno;

use Ds\Map;
use Infra\Database\Pool\IPDOPool;
use OpenSwoole\Core\Coroutine\Client\PDOConfig;
use OpenSwoole\Core\Coroutine\Pool\ClientPool;
use PDO;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;
use Infra\Logging\ILogger;

/**
 * {@see IPDOPool} implementation backed by OpenSwoole core's {@see ClientPool}.
 *
 * The whole point of {@see PooledPDOClient} is to get connection pooling while
 * still handing raw {@see PDO} to the transaction/repositories — so this pool
 * speaks the same {@see IPDOPool} contract as the raw-PDO stack: it leases a
 * pooled client internally, but `get()`/`put()` deal in raw `PDO`. Consumers
 * (the transaction session, repositories) never see the client and stay
 * unchanged. The underlying `ClientPool` still owns the client for its
 * proxy-only housekeeping (heartbeat/reconnect).
 *
 * On top of `ClientPool` this wrapper adds the project's conventions:
 *
 *  - a {@see Result}-based API instead of raw returns / blocking pops;
 *  - lease bookkeeping (raw PDO -> owning client) so an out-of-band `PDO`
 *    cannot be returned, and so the correct client goes back to the pool;
 *  - channel-scoped logging.
 */
final class OpenSwoolePDOClientPool implements IPDOPool
{
    private ClientPool $pool;
    private ILogger $logger;

    /**
     * Active leases keyed by the raw PDO's object id, mapping to the pooled
     * client that owns it (so `put(PDO)` can return the right client).
     *
     * @var Map<int, PooledPDOClient>
     */
    private Map $activeLeases;

    public function __construct(
        PDOConfig $config,
        int $maxPoolSize,
        ILogger $logger,
        private readonly float $getTimeout = -1,
        bool $heartbeat = false,
    ) {
        $this->pool         = new ClientPool(PooledPDOClientFactory::class, $config, $maxPoolSize, $heartbeat);
        $this->logger       = $logger->withChannel('pdo-client-pool');
        $this->activeLeases = new Map();
    }

    public function get(): Result
    {
        $client = $this->pool->get($this->getTimeout);

        if (!$client instanceof PooledPDOClient) {
            $context = new LeafContext(
                message: 'Timed out while acquiring a PDOClient from the pool',
                code: 503,
            );
            $this->logger->error($context->message);
            return Result::failure(Leaf::newError($context));
        }

        $pdo = $client->getPdo();
        $this->activeLeases->put(spl_object_id($pdo), $client);

        return Result::success($pdo);
    }

    public function put(PDO $pdo): Result
    {
        $pdoId = spl_object_id($pdo);

        if (!$this->activeLeases->hasKey($pdoId)) {
            return Result::failure(Leaf::newError(new LeafContext(
                message: 'Invalid PDO return',
                code: 409,
            )));
        }

        $client = $this->activeLeases->get($pdoId);
        $this->activeLeases->remove($pdoId);
        $this->pool->put($client);

        return Result::void();
    }
}
