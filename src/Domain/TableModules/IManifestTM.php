<?php

namespace Domain\TableModules;

use Domain\Models\IManifestCargo;
use Domain\Models\IContainer;
use Domain\Models\IProduct;
use Shared\Exceptions\Result;

interface IManifestTM
{
    /**
     * Loads a quantity of a product into the container: computes the added
     * weight (density × quantity), validates status and capacity, transitions
     * Empty→Loading, and records a Load telemetry event.
     *
     * @param  IManifestCargo|null  $current  The product's existing cargo line, if any.
     * @return Result<\Domain\Models\IManifestChange> Failure 409/422 on rule violations.
     */
    public function load(IContainer $container, IProduct $product, float $quantity, ?IManifestCargo $current): Result;

    /**
     * Unloads a quantity: requires status Loading, reduces the cargo and the
     * container weight; when the container empties, status returns to Empty and
     * the whole manifest is cleared. Records an Unload telemetry event.
     *
     * @param  IManifestCargo|null  $current  The product's existing cargo line, if any.
     * @return Result<\Domain\Models\IManifestChange> Failure 409/422 on rule violations.
     */
    public function unload(IContainer $container, IProduct $product, float $quantity, ?IManifestCargo $current): Result;
}
