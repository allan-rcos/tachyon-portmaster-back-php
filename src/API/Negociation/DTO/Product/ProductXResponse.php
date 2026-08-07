<?php

/**
 * Product Response Message.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation\DTO\Product;

use Domain\Enums\RiskClass;

/**
 * A product, alone or as a row of a page.
 *
 * @see ProductXResponseFactory What renders this onto the wire.
 * @see ProductListXResponse Where it is nested.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class ProductXResponse
{
    /**
     * @param  ?string  $id  Base62 identifier.
     * @param  ?string  $name  Display name.
     * @param  float  $density  Density in t/m³.
     * @param  RiskClass  $riskClass  UN hazard class.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ?string $id = null,
        public ?string $name = null,
        public float $density = 0.0,
        public RiskClass $riskClass = RiskClass::Class1Explosives,
    ) {
    }
}
