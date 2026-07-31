<?php

/**
 * Manifest Change.
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

use Domain\Enums\TelemetryEvent;
use Domain\Models\IContainer;
use Domain\Models\IManifestCargo;
use Domain\Models\IManifestChange;

/**
 * Concrete {@see IManifestChange} — every consequence of a load or unload,
 * computed by {@see \Domain\TableModules\IManifestTM}.
 *
 * The use case persists each part and makes no decisions of its own.
 *
 * @see IManifestChange What each property means.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class ManifestChange implements IManifestChange
{
    /**
     * @param  IContainer  $container  New container state — weight, and the
     *                                 status transition if the move caused one.
     * @param  string  $productId  Product that moved.
     * @param  ?IManifestCargo  $cargo  Resulting cargo line, or null to remove it.
     * @param  bool  $clearManifest  True when the container emptied entirely and
     *                               the whole manifest should be dropped.
     * @param  TelemetryEvent  $event  What to record in the telemetry log.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public IContainer $container,
        public string $productId,
        public ?IManifestCargo $cargo,
        public bool $clearManifest,
        /** What the telemetry log records for this move. */
        public TelemetryEvent $event,
    ) {
    }
}
