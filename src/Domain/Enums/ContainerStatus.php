<?php

declare(strict_types=1);

namespace Domain\Enums;

use ValueError;

/**
 * Lifecycle status of a container, as a string-backed enum.
 *
 * The string value is the application/JSON representation; {@see toInt()} /
 * {@see fromInt()} convert to the FlatBuffer `uint8` wire value at the proxy
 * boundary. DECLARATION ORDER MUST MATCH the `ContainerStatus` enum in
 * `swagger/flatbuffers/schemas/common.fbs`.
 */
enum ContainerStatus: string
{
    case Empty = 'empty';
    case Loading = 'loading';
    case Sealed = 'sealed';
    case InTransit = 'in-transit';

    public function toInt(): int
    {
        foreach (self::cases() as $ordinal => $case) {
            if ($case === $this) {
                return $ordinal;
            }
        }

        return 0; // unreachable.
    }

    public static function fromInt(int $value): self
    {
        return self::cases()[$value] ?? throw new ValueError("Unknown ContainerStatus ordinal: {$value}");
    }
}
