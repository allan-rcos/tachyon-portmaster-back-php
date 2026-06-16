<?php

namespace Infra\Database;

use Domain\Ports\Core\IUnitOfWork;
use Ds\Map;
use Infra\Database\Pool\IPDOPool;
use OpenSwoole\Coroutine;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;
use Shared\Logging\ILogger;
use Throwable;

final readonly class PdoTransactionSession implements IUnitOfWork
{
    private const string CONTEXT_KEY = 'pdo_transaction_context';
    private ILogger $logger;

    public function __construct(
        private IPDOPool $dbPool,
        ILogger $logger,
    ) {
        $this->logger = $logger->withChannel("transaction-session");
    }

    public function begin(): Result
    {
        $cid = Coroutine::getCid();
        if ($cid === -1) {
            $context = new LeafContext(
                message: "Attempted to begin a transaction outside of a coroutine context. Ignoring.",
                code: 500,
            );
            $this->logger->error($context->message);
            return Result::failure(Leaf::newError($context));
        }

        $context = Coroutine::getContext($cid);
        if (isset($context[self::CONTEXT_KEY])) {
            $this->logger->warn("Attempted to begin a transaction while another transaction is already active in the current coroutine context. Ignoring.");
            return Result::void();
        }

        $result = $this->dbPool->get();
        if (!$result->isSuccess()) return $result;
        $pdo = $result->getValue();
        $pdo->beginTransaction();

        $context[self::CONTEXT_KEY] = new PdoTransactionContext($pdo);

        Coroutine::defer(function() use ($context) {
            $this->executeRollbackAndRelease($context);
        });
        return Result::void();
    }

    private function executeRollbackAndRelease(mixed $context): Result
    {
        if (!isset($context[self::CONTEXT_KEY])) {
            return Result::void();
        }

        /** @var PdoTransactionContext $sessionContext */
        $sessionContext = $context[self::CONTEXT_KEY];

        try {
            if ($sessionContext->pdo->inTransaction()) {
                $sessionContext->pdo->rollBack();
            }
        } catch (Throwable $e) {
            $context = new LeafContext(
                message: "Failed to rollback transaction.",
                details: new Map([
                    "error_message" => $e->getMessage(),
                    "error_code" => $e->getCode(),
                ]),
                code: 500,
            );
            $this->logger->error($context->message,
                $context->details->toArray());
            return Result::failure(Leaf::newError($context));
        } finally {
            $this->dbPool->put($sessionContext->pdo);
            unset($context[self::CONTEXT_KEY]);
        }
        return Result::void();
    }

    public function rollback(): Result
    {
        $cid = Coroutine::getCid();
        if ($cid === -1) return Result::void();

        $context = Coroutine::getContext($cid);
        return $this->executeRollbackAndRelease($context);
    }

    public function commit(): Result
    {
        $cid = Coroutine::getCid();
        if ($cid === -1) {
            $this->logger->warn("Attempted to commit a transaction outside of a coroutine context. Ignoring.");
            return Result::void();
        }

        $context = Coroutine::getContext($cid);
        if (!isset($context[self::CONTEXT_KEY])) return Result::void();

        /** @var PdoTransactionContext $sessionContext */
        $sessionContext = $context[self::CONTEXT_KEY];

        try {
            if ($sessionContext->pdo->inTransaction()) {
                $sessionContext->pdo->commit();
            }
        } catch (Throwable $e) {
            $context = new LeafContext(
                message: "Failed to commit transaction.",
                details: new Map([
                    "error_message" => $e->getMessage(),
                    "error_code" => $e->getCode(),
                ]),
                code: 500,
            );
            $this->logger->error($context->message,
                $context->details->toArray());
            $result = $this->executeRollbackAndRelease($context);
            if (!$result->isSuccess()) return $result;
            return Result::failure(Leaf::newError($context));
        } finally {
            if (isset($context[self::CONTEXT_KEY])) {
                $this->dbPool->put($sessionContext->pdo);
                unset($context[self::CONTEXT_KEY]);
            }
        }
        return Result::void();
    }

    public function getTransaction(): Result
    {
        $cid = Coroutine::getCid();
        if ($cid === -1) {
            return Result::failure(Leaf::newError(new LeafContext(
                message: "No active coroutine found.",
                code: 500,
            )));
        }

        $context = Coroutine::getContext($cid);
        if (!isset($context[self::CONTEXT_KEY])) {
            return Result::failure(Leaf::newError(new LeafContext(
                message: "No active transaction found in the current coroutine context.",
                code: 500,
            )));
        }
        /** @var PdoTransactionContext $sessionContext */
        $sessionContext = $context[self::CONTEXT_KEY];

        return Result::success($sessionContext->pdo);
    }
}