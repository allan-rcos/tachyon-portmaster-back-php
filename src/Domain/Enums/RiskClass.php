<?php

declare(strict_types=1);

namespace Domain\Enums;

use ValueError;

/**
 * UN dangerous-goods risk class, as a string-backed enum.
 *
 * The string value is the application/JSON representation; {@see toInt()} /
 * {@see fromInt()} convert to the FlatBuffer `uint8` wire value at the proxy
 * boundary. DECLARATION ORDER MUST MATCH the `RiskClass` enum in
 * `swagger/flatbuffers/schemas/common.fbs`.
 */
enum RiskClass: string
{
    case Class1Explosives = 'class-1-explosives';
    case Class2Gases = 'class-2-gases';
    case Class3FlammableLiquids = 'class-3-flammable-liquids';
    case Class4FlammableSolids = 'class-4-flammable-solids';
    case Class5OxidizingSubstances = 'class-5-oxidizing-substances';
    case Class6ToxicSubstances = 'class-6-toxic-substances';
    case Class7RadioactiveMaterials = 'class-7-radioactive-materials';
    case Class8CorrosiveSubstances = 'class-8-corrosive-substances';
    case Class9Miscellaneous = 'class-9-miscellaneous';
    case None = 'none';

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
        return self::cases()[$value] ?? throw new ValueError("Unknown RiskClass ordinal: {$value}");
    }
}
