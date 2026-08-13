<?php

/**
 * Container Status Enum.
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
 * Lifecycle status of a container.
 *
 * The transitions are one-way — empty to loading to sealed to in-transit — and
 * `ContainerTM` is what enforces them. A container cannot be sealed before it
 * is loaded, cannot be loaded once sealed, and cannot be dispatched twice.
 *
 * The string value is the application/JSON representation; {@see toInt()} and
 * {@see fromInt()} convert to the FlatBuffer `uint8` wire value at the factory
 * boundary. **Declaration order must match** the `ContainerStatus` enum in
 * `swagger/flatbuffers/schemas/common.fbs`, because the conversion is by
 * ordinal.
 *
 * @see \Domain\TableModules\Interno\ContainerTM Enforces the transitions.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
enum ContainerStatus: string
{
    /**
     * Registered and holding nothing. Cannot be sealed.
     */
    case Empty = 'empty';

    /**
     * Holding cargo and still accepting more.
     */
    case Loading = 'loading';

    /**
     * Closed and past the minimum fill. Takes no further cargo; the only move
     * left is dispatch.
     */
    case Sealed = 'sealed';

    /**
     * Dispatched. Terminal — nothing transitions out of it.
     */
    case InTransit = 'in-transit';

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
     * Throws rather than returning a fallback: an unknown ordinal means the
     * schema and this enum have diverged, which is a deployment fault, not
     * something a request should be allowed to proceed past.
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
        return self::cases()[$value] ?? throw new ValueError("Unknown ContainerStatus ordinal: {$value}");
    }
}
