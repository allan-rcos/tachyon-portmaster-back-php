<?php

namespace Domain\Models;

/**
 * The full outcome of a manifest load/unload, computed by the manifest table
 * module. The use case just persists each part — it makes no decisions.
 *
 * - {@see $container}: the container's new state (weight + status transition).
 * - {@see $cargo}: the product's resulting cargo line, or null to remove that
 *   line (fully unloaded).
 * - {@see $clearManifest}: true when the container emptied — drop the whole
 *   manifest.
 * - {@see $event}: the slug of the telemetry event to record.
 */
interface IManifestChange
{
    public IContainer $container {
        get;
    }

    public string $productId {
        get;
    }

    public ?IManifestCargo $cargo {
        get;
    }

    public bool $clearManifest {
        get;
    }

    public string $event {
        get;
    }
}
