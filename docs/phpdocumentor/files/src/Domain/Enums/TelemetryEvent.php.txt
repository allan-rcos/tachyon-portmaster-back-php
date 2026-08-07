<?php

/**
 * Telemetry Event Enum.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Domain\Enums;

use ValueError;

/**
 * What a telemetry log entry records happening to a container.
 *
 * A closed set fixed by the business, like {@see ContainerStatus} and
 * {@see RiskClass} — the container domain decides what is worth recording, and
 * a new case is a code change, not a configuration one. That is what separates
 * it from {@see \Domain\Models\IPermission}, which really is a runtime registry:
 * a permission exists because a use case declared it, and the set is not
 * knowable from the schema.
 *
 * The string value is the application/JSON representation and what
 * `telemetry_logs.event` stores; {@see toInt()} and {@see fromInt()} convert to
 * the FlatBuffer `uint8` wire value at the factory boundary. **Declaration order
 * must match** the `TelemetryEvent` enum in
 * `swagger/flatbuffers/schemas/common.fbs`, because the conversion is by
 * ordinal.
 *
 * @see \Domain\TableModules\IManifestTM What decides which case a change carries.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
enum TelemetryEvent: string
{
    /**
     * Cargo was added to the container.
     */
    case Load = 'load';

    /**
     * Cargo was removed from the container.
     */
    case Unload = 'unload';

    /**
     * The FlatBuffer ordinal for this case.
     *
     * @return int Zero-based position in declaration order.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function toInt(): int
    {
        foreach (self::cases() as $ordinal => $case) {
            if ($case === $this) {
                return $ordinal;
            }
        }

        return 0; // unreachable.
    }

    /**
     * The case at a FlatBuffer ordinal.
     *
     * Throws rather than returning a fallback, on the same terms as
     * {@see RiskClass::fromInt()}: an unknown ordinal means the schema and this
     * enum have diverged, which is a deployment fault.
     *
     * @param  int  $value  Zero-based ordinal read off the wire.
     * @return self The matching case.
     *
     * @throws ValueError When the ordinal is outside the declared range.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function fromInt(int $value): self
    {
        return self::cases()[$value] ?? throw new ValueError("Unknown TelemetryEvent ordinal: {$value}");
    }
}
