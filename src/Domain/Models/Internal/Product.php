<?php

/**
 * Product.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

namespace Domain\Models\Internal;

use Domain\Enums\RiskClass;
use Domain\Models\IProduct;

/**
 * Concrete {@see IProduct}. Built only by
 * {@see \Domain\TableModules\IProductTM}, which validates it first.
 *
 * @see IProduct What each property means.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
class Product implements IProduct
{
    /**
     * @param  string  $id  Application-generated Snowflake.
     * @param  string  $name  Commercial name, already validated as non-blank.
     * @param  float  $density  Kilograms per litre.
     * @param  RiskClass  $riskClass  UN classification, or `None`.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public string $id {
            get => $this->id;
        },
        public string $name {
            get => $this->name;
        },
        public float $density {
            get => $this->density;
        },
        public RiskClass $riskClass {
            get => $this->riskClass;
        },
    ) {
    }
}
