<?php

/**
 * Product Update Request Message.
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
 * A product being edited.
 *
 * @see ProductUpdateXRequestFactory What builds this from a request body.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class ProductUpdateXRequest
{
    /**
     * @param  ?string  $name  Display name.
     * @param  float  $density  Density in t/m³.
     * @param  RiskClass  $riskClass  UN hazard class.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ?string $name = null,
        public float $density = 0.0,
        public RiskClass $riskClass = RiskClass::Class1Explosives,
    ) {
    }
}
