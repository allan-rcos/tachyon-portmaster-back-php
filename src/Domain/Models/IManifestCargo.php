<?php

namespace Domain\Models;

/**
 * One product's cargo line in a container: the resulting state after a
 * load/unload, computed by the manifest table module.
 */
interface IManifestCargo
{
    public string $containerId {
        get;
    }

    public string $productId {
        get;
    }

    public float $quantity {
        get;
    }

    public float $weight {
        get;
    }
}
