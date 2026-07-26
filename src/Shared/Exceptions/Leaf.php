<?php

/**
 * Exception Registry Manager Module.
 *
 * A module structured to handle mapping states logic execution map boundaries tracking setups implementations mapping layout.
 *
 * @category Shared\Exceptions
 *
 * @since 0.0.1 File creation.
 *
 * @version 0.0.1
 *
 * @license {@link https://www.gnu.org/licenses/gpl-3.0.pt-br.html GPL-3}
 * @copyright 2026 Ricardo Állan Costa
 * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
 *
 * @filesource
 */

namespace Shared\Exceptions;

use OpenSwoole\Coroutine;
use OpenSwoole\Coroutine\Context;

/**
 * Global and coroutine-aware exception registry implementation.
 *
 * Binds errors to request definitions states implementations setup map contexts wrapping block instances bounds values setup contexts context operations layout paths rules executions map setup definitions map bounds contexts values states operations maps maps operations mappings bounds structures bounds map boundaries tracking properties rules implementations.
 *
 * @license {@link https://www.gnu.org/licenses/gpl-3.0.pt-br.html GPL-3}
 * @copyright 2026 Ricardo Állan Costa
 * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
 *
 * @since 0.0.1 File creation.
 *
 * @version 0.0.1
 */
class Leaf
{
    private static int $globalErrorId = 0;

    /**
     * Fallback store for errors raised **outside** a coroutine.
     *
     * The coroutine context is the right home during a request: it isolates
     * concurrent requests and is freed with them. But errors also occur where no
     * coroutine exists — the composition root at WorkerStart, CLI entry points,
     * and unit tests — and there the context write is silently dropped, leaving
     * {@see getError()} to return null and the caller to report "unknown error".
     * This keeps those recoverable.
     *
     * @var array<int, LeafContext>
     */
    private static array $processErrors = [];

    /**
     * Cap for {@see $processErrors}. The fallback is only exercised off the
     * request path, where a handful of entries is the norm; the bound exists so a
     * pathological caller cannot grow it without limit in a long-lived worker.
     */
    private const int PROCESS_ERROR_LIMIT = 256;

    /**
     * Stashes a given custom error against Coroutine scope and registers its ID.
     *
     * @param  LeafContext  $errorObject  The custom domain mapped error structure.
     * @return int Stored correlated trace error ID for logging.
     * @since 0.0.1 File creation.
     *
     * @version 0.0.1
     *
     * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
     * @copyright 2026 Ricardo Állan Costa
     *
     */
    public static function newError(LeafContext $errorObject): int
    {
        self::$globalErrorId++;

        $cid = Coroutine::getCid();

        if ($cid > 0) {
            /** @var Context|null $context */
            $context = Coroutine::getContext($cid);
            if ($context !== null) {
                /** @var array<int, LeafContext> $errors */
                $errors = isset($context['__leaf_errors']) ? $context['__leaf_errors'] : [];
                $errors[self::$globalErrorId] = $errorObject;
                $context['__leaf_errors'] = $errors;

                return self::$globalErrorId;
            }
        }

        // No coroutine context to bind to (boot, CLI, tests): keep it in-process
        // so the error stays retrievable instead of vanishing.
        if (count(self::$processErrors) >= self::PROCESS_ERROR_LIMIT) {
            array_shift(self::$processErrors);
        }
        self::$processErrors[self::$globalErrorId] = $errorObject;

        return self::$globalErrorId;
    }

    /**
     * Retrieves the stored specific logical exception back based upon contextual requested identifier.
     *
     * @param  int  $errorId  Identifier mapped.
     * @return LeafContext|null Retrieved previously error item natively.
     * @since 0.0.1 File creation.
     *
     * @version 0.0.1
     *
     * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
     * @copyright 2026 Ricardo Állan Costa
     *
     */
    public static function getError(int $errorId): ?LeafContext
    {
        $cid = Coroutine::getCid();
        if ($cid > 0) {
            /** @var Context|null $context */
            $context = Coroutine::getContext($cid);
            if ($context !== null && isset($context['__leaf_errors']) && is_array($context['__leaf_errors'])) {
                $error = $context['__leaf_errors'][$errorId] ?? null;
                if ($error instanceof LeafContext) {
                    return $error;
                }
            }
        }

        return self::$processErrors[$errorId] ?? null;
    }

    /**
     * Drops the out-of-coroutine errors. Intended for tests, which would
     * otherwise accumulate them across cases in one process.
     */
    public static function flushProcessErrors(): void
    {
        self::$processErrors = [];
    }
}