<?php

/**
 * Manifest Cargo Contract.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

namespace Domain\Models;

/**
 * One product's cargo line in a container: the resulting state after a load or
 * unload, computed by the manifest table module.
 *
 * A *result*, not a request — the caller says how much to move and the table
 * module answers with the line as it now stands.
 *
 * @see \Domain\TableModules\IManifestTM Computes these.
 * @see IManifestChange The full outcome this is one part of.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IManifestCargo
{
    /**
     * @var string Id of the container holding the cargo.
     */
    public string $containerId {
        get;
    }

    /**
     * @var string Id of the product loaded.
     */
    public string $productId {
        get;
    }

    /**
     * @var float Units of the product now in the container. Always positive —
     *            a line reaching zero is removed rather than stored.
     */
    public float $quantity {
        get;
    }

    /**
     * @var float What {@see $quantity} weighs, from the product's density. This
     *            is what counts against the container's capacity.
     */
    public float $weight {
        get;
    }
}
