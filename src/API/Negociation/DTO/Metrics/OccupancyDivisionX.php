<?php

/**
 * Occupancy Division Message.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation\DTO\Metrics;

/**
 * How the yard's containers split across their statuses.
 *
 * @see OccupancyDivisionXFactory What renders this onto the wire.
 * @see MetricsXResponse Where it is nested.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class OccupancyDivisionX
{
    /**
     * @param  int  $empty  Containers holding nothing.
     * @param  int  $loading  Containers being filled.
     * @param  int  $sealed  Containers closed and waiting.
     * @param  int  $inTransit  Containers already dispatched.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public int $empty = 0,
        public int $loading = 0,
        public int $sealed = 0,
        public int $inTransit = 0,
    ) {
    }
}
