<?php

/**
 * Telemetry Log Item Message.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation\DTO\Container;

use Domain\Enums\TelemetryEvent;

/**
 * One line of a container's recent history.
 *
 * @see TelemetryLogItemXFactory What renders this onto the wire.
 * @see ContainerSummaryXResponse Where it is nested.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class TelemetryLogItemX
{
    /**
     * @param  ?string  $id  Base62 identifier of the log entry.
     * @param  TelemetryEvent  $event  What happened.
     * @param  ?string  $description  How it read to a human.
     * @param  ?string  $timestamp  When, as an ISO-8601 string.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ?string $id = null,
        public TelemetryEvent $event = TelemetryEvent::Load,
        public ?string $description = null,
        public ?string $timestamp = null,
    ) {
    }
}
