<?php

/**
 * Result.
 *
 * @category Shared
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

namespace Shared\Exceptions;

/**
 * The return type of every operation that can fail.
 *
 * A Result is either a success or a failure carrying an error id — never both,
 * and never neither. A success usually carries a value; {@see void()} is the
 * one that carries nothing, and means "no error", never "the value is null".
 * Failures travel as ids rather than as thrown exceptions so a use case can
 * decide what to do with one without unwinding the stack, which matters when
 * the boundary in between is a database transaction that must be rolled back
 * rather than abandoned.
 *
 * The id indexes into {@see Leaf}, which holds the {@see LeafContext} carrying
 * the message, details and HTTP status. Keeping that context out of the Result
 * is what lets a failure be typed `Result<never>` and so satisfy any signature
 * it is propagated through.
 *
 * @see Leaf The registry an error id points into.
 * @see LeafContext What an error id resolves to.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @template-covariant T
 */
final readonly class Result
{
    /**
     * Private so a Result can only be built through the three named
     * constructors, none of which can produce an inconsistent state.
     *
     * @param  T  $value  The success value; null on a failure.
     * @param  int  $errorId  Index into {@see Leaf}; -1 on a success.
     * @param  bool  $isSuccess  Which of the two this instance is.
     *
     * @copyright 2026 Tachyon
     */
    private function __construct(
        private mixed $value,
        private int $errorId,
        private bool $isSuccess
    ) {
    }

    /**
     * Wraps a value as a success.
     *
     * @template TValue
     *
     * @param  TValue  $value  The value the caller asked for.
     * @return self<TValue> A success carrying it.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function success(mixed $value): self
    {
        /** @var self<TValue> $instance */
        $instance = new self($value, -1, true);

        return $instance;
    }

    /**
     * Wraps a registered error id as a failure.
     *
     * @param  int  $errorId  Index returned by {@see Leaf::newError()}.
     * @return Result<never> A failure carries no value, so it stands in for a
     *                       success of any type — which is what lets a use case
     *                       propagate one straight out of a differently-typed
     *                       method.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function failure(int $errorId): self
    {
        /** @var self<never> $instance */
        $instance = new self(null, $errorId, false);

        return $instance;
    }

    /**
     * A success with nothing to return — the shape of a command that worked,
     * and of a question whose honest answer is "nothing", such as a lookup that
     * matched no row where matching none is not a fault.
     *
     * It means **no error**, and never "the value is null": a null on a success
     * carries no information, so nothing should read one. Ask {@see isEmpty()}
     * instead, then take the branch.
     *
     * Typed `Result<never>` for the same reason {@see failure()} is: carrying no
     * value, it satisfies a signature of any type, so a method declared
     * `Result<IProduct>` can answer one without widening to `IProduct|null`.
     *
     * @return Result<never> A success carrying nothing.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function void(): self
    {
        /** @var self<never> $instance */
        $instance = new self(null, -1, true);

        return $instance;
    }

    /**
     * Whether this is a success that carries nothing — what {@see void()}
     * produces.
     *
     * The counterpart to {@see isSuccess()}, for the callers that have a branch
     * for "nothing came back" and must not reach {@see getValue()} to find out.
     * A success that is not empty always carries its value.
     *
     * @return bool True for a void success, false for any other Result.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function isEmpty(): bool
    {
        return $this->isSuccess && $this->value === null;
    }

    /**
     * Whether this is a success.
     *
     * Always the first thing a caller checks: {@see getErrorId()} is only
     * meaningful when this returns false, and {@see getValue()} only when it
     * returns true *and* {@see isEmpty()} returns false. A caller with no
     * branch for nothing does not need the second check — the operations that
     * can answer {@see void()} say so.
     *
     * @return bool True for a success, false for a failure.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function isSuccess(): bool
    {
        return $this->isSuccess;
    }

    /**
     * The value carried by a success.
     *
     * Null on a failure and on a {@see void()} success, and in neither case
     * does that null mean anything: it is the absence of a value, not a value.
     * Check {@see isSuccess()} — and {@see isEmpty()} where nothing is a
     * possible answer — rather than reading this to find out which happened.
     *
     * @return T The value; null when there is none.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * The error id carried by a failure, for {@see Leaf::getError()}.
     *
     * @return int The registered id, or -1 when this is a success.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function getErrorId(): int
    {
        return $this->errorId;
    }
}
