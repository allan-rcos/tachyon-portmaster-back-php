<?php

/**
 * Result Objects Implementation Pattern.
 *
 * A module representing operations execution wrapping outputs logic states instances bounds.
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

/**
 * Generic result pattern wrapper carrying standard operations state context natively across the stack definitions.
 *
 * Controls output bounds setup boundaries implementations map contexts.
 *
 * @license {@link https://www.gnu.org/licenses/gpl-3.0.pt-br.html GPL-3}
 * @copyright 2026 Ricardo Állan Costa
 * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
 *
 * @since 0.0.1 File creation.
 *
 * @version 0.0.1
 *
 * @template T
 */
readonly class Result
{
    /**
     * @param  T  $value
     * @param  int  $errorId
     * @param  bool  $isSuccess
     */
    private function __construct(
        private mixed $value,
        private int $errorId,
        private bool $isSuccess
    ) {
    }

    /**
     * Instantiates returning valid content wrapper implementation instances globally.
     *
     * @param  T  $value  Resolved associated inner content payload extracted definition structure mapped implementation context representation tracking details wrapper item mapped layout operations.
     * @return Result<T> Generated new class state payload details execution wrapper implementation response context.
     * @since 0.0.1 File creation.
     *
     * @version 0.0.1
     *
     * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
     * @copyright 2026 Ricardo Állan Costa
     *
     */
    public static function success(mixed $value): self
    {
        /** @var self<T> $instance */
        $instance = new self($value, -1, true);
        return $instance;
    }

    /**
     * Sets related structured fail path identifying tracing context.
     *
     * @param  int  $errorId  Expected specific detailed pointer index wrapper implementation block.
     * @return Result<null> Failure details operations context state reference details implementation class structure mapped path definitions representation response object instance equivalent map structure defined result layout pattern execution payload.
     * @since 0.0.1 File creation.
     *
     * @version 0.0.1
     *
     * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
     * @copyright 2026 Ricardo Állan Costa
     *
     */
    public static function failure(int $errorId): self
    {
        /** @var self<null> $instance */
        $instance = new self(null, $errorId, false);
        return $instance;
    }

    /**
     * Instantiates empty result instance representing non-mapped state or uninitialized content wrapper implementation.
     *
     * @return Result<null> Generated new class state payload details execution wrapper implementation response context representing empty content mapping.
     * @since 0.0.1 File creation.
     *
     * @version 0.0.1
     *
     * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
     * @copyright 2026 Ricardo Állan Costa
     *
     */
    public static function void(): self
    {
        /** @var self<null> $instance */
        $instance = new self(null, -1, true);
        return $instance;
    }

    /**
     * Confirms implementation definitions structure instance resolution.
     *
     * @return bool True if mapped state represented successful valid trace layout implementation path.
     * @copyright 2026 Ricardo Állan Costa
     *
     * @since 0.0.1 File creation.
     *
     * @version 0.0.1
     *
     * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
     */
    public function isSuccess(): bool
    {
        return $this->isSuccess;
    }

    /**
     * Resolves generic inner data value context wrapper tracking operations definitions mapped block representation payload details.
     *
     * @return T Requested operations output value mapping object response instance equivalent map instance payload data structure result details operations operations state implementations definition implementations valid content mapping implementation path equivalent block structure tracking payload setup definitions map instance wrapper payload operations setup implementations mapped wrapper class state operations.
     * @copyright 2026 Ricardo Állan Costa
     *
     * @since 0.0.1 File creation.
     *
     * @version 0.0.1
     *
     * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * Gets internally stored invalid structure path pointing implementations error mapped index block sequence definitions.
     *
     * @return int Equivalent details pointer class execution setup definitions map context equivalent layout response output mapped state execution trace.
     * @copyright 2026 Ricardo Állan Costa
     *
     * @since 0.0.1 File creation.
     *
     * @version 0.0.1
     *
     * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
     */
    public function getErrorId(): int
    {
        return $this->errorId;
    }
}