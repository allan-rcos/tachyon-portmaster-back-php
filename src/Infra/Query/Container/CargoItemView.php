<?php

/**
 * Cargo Item View.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra\Query\Container;

/**
 * One line of a container's manifest: which product, how much of it, and how
 * heavy.
 *
 * Carries the product's name alongside its id, joined in by the query, so a
 * caller rendering a manifest does not look up each product separately.
 *
 * @see ContainerSummaryViewItem What carries a list of these.
 * @see \Infra\Query\Interno\ListContainerSummariesDQL What builds them.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class CargoItemView
{
    /**
     * @param  string  $productId  Base62, already encoded for the caller.
     * @param  string  $productName  As stored on the product at query time, not
     *                               as it was when the cargo was loaded.
     * @param  float  $quantity  How many units the container holds.
     * @param  float  $weight  What that quantity weighs, as stored on the line.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public string $productId,
        public string $productName,
        public float $quantity,
        public float $weight,
    ) {
    }
}
