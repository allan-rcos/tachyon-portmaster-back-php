<?php

/**
 * Cargo Manifest Item Message.
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
 * One product's line on a container's manifest.
 *
 * @see CargoManifestItemXFactory What renders this onto the wire.
 * @see ContainerSummaryXResponse Where it is nested.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class CargoManifestItemX
{
    /**
     * @param  ?string  $productId  Base62 id of the product.
     * @param  ?string  $productName  Its display name, denormalised for the screen.
     * @param  float  $quantity  How much of it is aboard, in m³.
     * @param  float  $weight  What that amounts to, in tonnes.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ?string $productId = null,
        public ?string $productName = null,
        public float $quantity = 0.0,
        public float $weight = 0.0,
    ) {
    }
}
