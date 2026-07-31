<?php

/**
 * Manifest Change Contract.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

namespace Domain\Models;

use Domain\Enums\TelemetryEvent;

/**
 * The full outcome of a manifest load or unload, computed by the manifest table
 * module.
 *
 * Every consequence of moving cargo is decided here and handed over as one
 * object: the container's new weight and status, the affected cargo line, and
 * whether the manifest emptied. The use case persists each part and makes no
 * decisions of its own — which is what keeps the arithmetic and the status
 * transitions in a single place that a unit test can reach directly.
 *
 * @see \Domain\TableModules\IManifestTM Computes these.
 * @see \App\Services\ILoadItemUseCase Persists them.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IManifestChange
{
    /**
     * @var IContainer The container's new state — updated weight, and the
     *                 status transition if the move caused one.
     */
    public IContainer $container {
        get;
    }

    /**
     * @var string Id of the product that moved.
     */
    public string $productId {
        get;
    }

    /**
     * @var ?IManifestCargo The product's resulting cargo line, or null to remove
     *                      it — meaning that product was fully unloaded.
     */
    public ?IManifestCargo $cargo {
        get;
    }

    /**
     * @var bool True when the container emptied entirely and the whole manifest
     *           should be dropped, rather than one line removed.
     */
    public bool $clearManifest {
        get;
    }

    /**
     * @var TelemetryEvent What to record in the telemetry log for this move.
     */
    public TelemetryEvent $event {
        get;
    }
}
