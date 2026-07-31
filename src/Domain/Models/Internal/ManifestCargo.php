<?php

/**
 * Manifest Cargo.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Domain\Models\Internal;

use Domain\Models\IManifestCargo;

/**
 * Concrete {@see IManifestCargo} — the resulting state of one product's cargo
 * line after a load or unload, computed by
 * {@see \Domain\TableModules\IManifestTM}.
 *
 * @see IManifestCargo What each property means.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class ManifestCargo implements IManifestCargo
{
    /**
     * @param  string  $containerId  Container holding the cargo.
     * @param  string  $productId  Product loaded.
     * @param  float  $quantity  Units now in the container; always positive.
     * @param  float  $weight  What that quantity weighs, from the product's density.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public string $containerId,
        public string $productId,
        public float $quantity,
        public float $weight,
    ) {
    }
}
