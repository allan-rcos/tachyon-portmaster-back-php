<?php

/**
 * Occupancy View.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra\Query\Metrics;

/**
 * How many containers sit in each status.
 *
 * One field per {@see \Domain\Enums\ContainerStatus} member, counted
 * independently, so the four together account for every container and sum to
 * {@see MetricsView::$totalContainers}. A status introduced to the enum needs a
 * field here too — the shape is fixed rather than keyed by the enum, so the
 * response is a stable object rather than a map that grows.
 *
 * @see MetricsView What carries this.
 * @see \Infra\Query\Interno\MetricsDQL What builds one.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class OccupancyView
{
    /**
     * @param  int  $empty  Carrying nothing.
     * @param  int  $loading  Being filled.
     * @param  int  $sealed  Closed and awaiting departure.
     * @param  int  $inTransit  Already on their way.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public int $empty,
        public int $loading,
        public int $sealed,
        public int $inTransit,
    ) {
    }
}
