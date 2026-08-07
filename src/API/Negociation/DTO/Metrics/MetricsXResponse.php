<?php

/**
 * Metrics Response Message.
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
 * The yard dashboard's numbers.
 *
 * @see MetricsXResponseFactory What renders this onto the wire.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class MetricsXResponse
{
    /**
     * @param  int  $activeContainers  Containers not yet dispatched.
     * @param  int  $totalContainers  Every container on record.
     * @param  float  $yardLoad  Occupancy as a fraction of capacity.
     * @param  int  $registeredProducts  Products in the catalogue.
     * @param  ?OccupancyDivisionX  $occupancyDivision  The split by status.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public int $activeContainers = 0,
        public int $totalContainers = 0,
        public float $yardLoad = 0.0,
        public int $registeredProducts = 0,
        public ?OccupancyDivisionX $occupancyDivision = null,
    ) {
    }
}
