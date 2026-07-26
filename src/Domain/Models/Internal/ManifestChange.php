<?php

declare(strict_types=1);

namespace Domain\Models\Internal;

use Domain\Models\IContainer;
use Domain\Models\IManifestCargo;
use Domain\Models\IManifestChange;

/**
 * The full outcome of a manifest load/unload, computed by {@see \Domain\TableModules\IManifestTM}.
 * The use case just persists each part — it makes no decisions.
 *
 * - {@see $container}: the container's new state (weight + status transition).
 * - {@see $cargo}: the product's resulting cargo line, or null to remove that
 *   line (fully unloaded).
 * - {@see $clearManifest}: true when the container emptied — drop the whole
 *   manifest.
 * - {@see $event}: the slug of the telemetry event to record.
 */
final readonly class ManifestChange implements IManifestChange
{
    public function __construct(
        public IContainer $container,
        public string $productId,
        public ?IManifestCargo $cargo,
        public bool $clearManifest,
        /** Telemetry event slug; see {@see \Domain\Models\ITelemetryEvent}. */
        public string $event,
    ) {
    }
}
