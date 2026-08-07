<?php

/**
 * Risk Class Enum.
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
 * UN dangerous-goods risk class of a product.
 *
 * The nine classes are the UN transport classification, with a tenth case for
 * goods that carry none. Stored on the product and reported in the yard
 * metrics; nothing restricts loading by class today.
 *
 * The string value is the application/JSON representation; {@see toInt()} and
 * {@see fromInt()} convert to the FlatBuffer `uint8` wire value at the factory
 * boundary. **Declaration order must match** the `RiskClass` enum in
 * `swagger/flatbuffers/schemas/common.fbs`, because the conversion is by
 * ordinal.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
enum RiskClass: string
{
    /**
     * Class 1 — explosives.
     */
    case Class1Explosives = 'class-1-explosives';

    /**
     * Class 2 — gases, compressed or liquefied.
     */
    case Class2Gases = 'class-2-gases';

    /**
     * Class 3 — flammable liquids.
     */
    case Class3FlammableLiquids = 'class-3-flammable-liquids';

    /**
     * Class 4 — flammable solids and substances liable to spontaneous combustion.
     */
    case Class4FlammableSolids = 'class-4-flammable-solids';

    /**
     * Class 5 — oxidizing substances and organic peroxides.
     */
    case Class5OxidizingSubstances = 'class-5-oxidizing-substances';

    /**
     * Class 6 — toxic and infectious substances.
     */
    case Class6ToxicSubstances = 'class-6-toxic-substances';

    /**
     * Class 7 — radioactive materials.
     */
    case Class7RadioactiveMaterials = 'class-7-radioactive-materials';

    /**
     * Class 8 — corrosive substances.
     */
    case Class8CorrosiveSubstances = 'class-8-corrosive-substances';

    /**
     * Class 9 — miscellaneous dangerous goods.
     */
    case Class9Miscellaneous = 'class-9-miscellaneous';

    /**
     * Not dangerous goods — the default for an ordinary product.
     */
    case None = 'none';

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
        return self::cases()[$value] ?? throw new ValueError("Unknown RiskClass ordinal: {$value}");
    }
}
