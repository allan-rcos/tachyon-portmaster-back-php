<?php

namespace Infra\Database\Interno;

use Infra\Database\IPdoTransaction;
use Infra\Database\IUnitOfWork;
use Ds\Map;
use Infra\Database\Pool\IPDOPool;
use OpenSwoole\Coroutine;
use OpenSwoole\Coroutine\Context;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;
use Infra\Logging\ILogger;
use Throwable;

/**
 * The database's unit of work, scoped to the current coroutine.
 *
 * Implements **both** halves of the split ({@see IUnitOfWork} and
 * {@see IPdoTransaction}) because here they really are the same resource — but
 * the provider hands each side out through its own contract, so a repository
 * only ever sees {@see getTransaction()} and a use case only ever sees
 * begin/commit/rollback.
 *
 * The open transaction lives in the coroutine context, which is what makes it
 * ambient: everything running inside one request shares it without passing it
 * around, and a `defer` releases the connection even if the request dies
 * mid-flight.
 */
final readonly class PdoTransactionSession implements IUnitOfWork, IPdoTransaction
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
        if ($context === null) {
            return Result::void();
        }
        if (isset($context[self::CONTEXT_KEY])) {
            $this->logger->warn("Attempted to begin a transaction while another transaction is already active in the current coroutine context. Ignoring.");
            return Result::void();
        }

        $result = $this->dbPool->get();
        if (!$result->isSuccess()) return Result::failure($result->getErrorId());
        $pdo = $result->getValue();
        $pdo->beginTransaction();

        $context[self::CONTEXT_KEY] = new PdoTransactionContext($pdo);

        Coroutine::defer(function() use ($context) {
            $this->executeRollbackAndRelease($context);
        });
        return Result::void();
    }

    /**
     * @return Result<null>
     */
    private function executeRollbackAndRelease(?Context $context): Result
    {
        if ($context === null || !isset($context[self::CONTEXT_KEY])) {
            return Result::void();
        }

        /** @var PdoTransactionContext $sessionContext */
        $sessionContext = $context[self::CONTEXT_KEY];

        try {
            if ($sessionContext->pdo->inTransaction()) {
                $sessionContext->pdo->rollBack();
            }
        } catch (Throwable $e) {
            $errorContext = new LeafContext(
                message: "Failed to rollback transaction.",
                details: new Map([
                    "error_message" => $e->getMessage(),
                    "error_code" => (string) $e->getCode(),
                ]),
                code: 500,
            );
            $this->logger->error($errorContext->message,
                ($errorContext->details?->toArray() ?? []));
            return Result::failure(Leaf::newError($errorContext));
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
        if (!$context instanceof Context) return Result::void();
        if (!isset($context[self::CONTEXT_KEY])) return Result::void();

        /** @var PdoTransactionContext $sessionContext */
        $sessionContext = $context[self::CONTEXT_KEY];

        try {
            if ($sessionContext->pdo->inTransaction()) {
                $sessionContext->pdo->commit();
            }
        } catch (Throwable $e) {
            $errorContext = new LeafContext(
                message: "Failed to commit transaction.",
                details: new Map([
                    "error_message" => $e->getMessage(),
                    "error_code" => (string) $e->getCode(),
                ]),
                code: 500,
            );
            $this->logger->error($errorContext->message,
                ($errorContext->details?->toArray() ?? []));
            $result = $this->executeRollbackAndRelease($context);
            if (!$result->isSuccess()) return Result::failure($result->getErrorId());
            return Result::failure(Leaf::newError($errorContext));
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