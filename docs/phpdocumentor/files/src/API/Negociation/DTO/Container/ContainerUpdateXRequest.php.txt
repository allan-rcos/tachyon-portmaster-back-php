<?php

/**
 * Container Update Request Message.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation\DTO\Container;

/**
 * A container being re-rated.
 *
 * Capacity is the only editable field: the code identifies a physical box and
 * the status is the yard's to change, never the caller's.
 *
 * @see ContainerUpdateXRequestFactory What builds this from a request body.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class ContainerUpdateXRequest
{
    /**
     * @param  float  $maxCapacity  How much it holds, in tonnes.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public float $maxCapacity = 0.0,
    ) {
    }
}
