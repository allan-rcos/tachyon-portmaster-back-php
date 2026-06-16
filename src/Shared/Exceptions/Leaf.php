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
            }
        }

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
        return null;
    }
}