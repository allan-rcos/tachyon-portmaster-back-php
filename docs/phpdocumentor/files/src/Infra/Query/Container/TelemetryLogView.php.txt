<?php

/**
 * Telemetry Log View.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra\Query\Container;

use Domain\Enums\TelemetryEvent;

/**
 * One entry of a container's telemetry log.
 *
 * The event is resolved to {@see \Domain\Enums\TelemetryEvent} here, so nothing
 * above this layer handles the stored string. A row whose stored value matches
 * no case cannot be resolved and is dropped by
 * {@see \Infra\Query\Interno\ListContainerSummariesDQL::logs()} — see there for
 * why dropping is preferred to inventing a case.
 *
 * @see ContainerSummaryViewItem What carries a list of these.
 * @see \Infra\Repository\IManifestRepository::insertTelemetry() What writes the rows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class TelemetryLogView
{
    /**
     * @param  string  $id  Base62 encoding of the row's auto-increment id.
     * @param  TelemetryEvent  $event  What the row records having happened.
     * @param  string|null  $description  Free text, or null when the entry was
     *                                    written without one.
     * @param  string|null  $timestamp  When the database stamped it, as an
     *                                  ISO-8601 UTC instant ending in `Z`; null
     *                                  when the column could not be read or did
     *                                  not parse. Every datetime in the system
     *                                  is UTC, and this is the only one that
     *                                  leaves the API — so it is rendered with
     *                                  the zone that says so rather than passed
     *                                  through as the driver's zone-less string.
     *                                  See {@see \Shared\Time\Utc}.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public string $id,
        public TelemetryEvent $event,
        public ?string $description,
        public ?string $timestamp,
    ) {
    }
}
