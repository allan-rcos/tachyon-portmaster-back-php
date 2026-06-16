<?php

namespace Infra\Database\Pool;

use Ds\Map;
use OpenSwoole\Coroutine\Channel;
use PDO;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;
use Shared\Logging\ILogger;

final readonly class PDOPool implements IPDOPool
{
    private Channel $pool;
    private ILogger $logger;
    private Map $activeLeases;

    public function __construct(
        private IPDOFactory $factory,
        private float $maxLeaseTime,
        private float $maxIdleTime,
        private int $maxPoolSize,
        ILogger $logger,
    ) {
        $this->logger = $logger->withChannel("db-pool");
        $this->pool = new Channel($this->maxPoolSize);
        $this->activeLeases = new Map();
    }

    public function get(): Result
    {
        $totalAllocated = $this->pool->length() + $this->activeLeases->count();

        if ($this->pool->isEmpty()) {
            if ($totalAllocated < $this->maxPoolSize) {
                $pdo = $this->factory->create();
                $this->activeLeases->put(spl_object_id($pdo),
                    new PDOLeaseToken($pdo));
                return Result::success($pdo);
            }

            if (!$this->activeLeases->isEmpty()) {
                $oldestPair = $this->activeLeases->first();
                /** @var PDOLeaseToken $oldestToken */
                $oldestToken = $oldestPair->value;
                if ($oldestToken->isExpired($this->maxLeaseTime)) {
                    $this->logger->warn("Leasing out PDO with ID $oldestPair->key for too long, force releasing it.");
                    $this->activeLeases->remove($oldestPair->key);
                    unset($token);

                    $newPdo = $this->factory->create();
                    $this->activeLeases->put(spl_object_id($newPdo),
                        new PDOLeaseToken($newPdo));
                    return Result::success($newPdo);
                }
            }
        }

        /** @var PDONode $node */
        $node = $this->pool->pop();
        $pdoId = spl_object_id($node->pdo);

        if ((microtime(true) - $node->lastActiveTime) > $this->maxIdleTime) {
            $this->logger->debug("PDO with ID $pdoId has been idle for too long, discarding it.");
            unset($node);
            return $this->get();
        }

        $this->activeLeases->put($pdoId, new PDOLeaseToken($node->pdo));

        return Result::success($node->pdo);
    }

    public function put(PDO $pdo): Result
    {
        $pdoId = spl_object_id($pdo);

        if (!$this->activeLeases->hasKey($pdoId)) {
            return Result::failure(Leaf::newError(new LeafContext(
                message: "Invalid PDO return",
                code: 409,
            )));
        }

        $this->activeLeases->remove($pdoId);
        $node = new PDONode($pdo);
        $this->pool->push($node);

        return Result::success(null);
    }
}