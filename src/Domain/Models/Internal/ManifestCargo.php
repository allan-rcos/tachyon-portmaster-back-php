<?php

declare(strict_types=1);

namespace Domain\Models\Internal;

use Domain\Models\IManifestCargo;

/**
 * The resulting state of one product's cargo line in a container after a
 * load/unload — computed by the manifest table module.
 */
final readonly class ManifestCargo implements IManifestCargo
{
    public function __construct(
        public string $containerId,
        public string $productId,
        public float $quantity,
        public float $weight,
    ) {
    }
}
