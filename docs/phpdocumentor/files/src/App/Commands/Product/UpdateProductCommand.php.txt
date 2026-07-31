<?php

/**
 * Update Product Command.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App\Commands\Product;

use App\Context\UserContext;

use Domain\Enums\RiskClass;

/**
 * Replaces one product's fields wholesale.
 *
 * Follows the command shape documented on {@see CreateProductCommand}. Every
 * field is required, not just the changed ones — this is a replacement, so a
 * caller sending only a new name would blank the rest.
 *
 * @see \App\Services\IUpdateProductUseCase What consumes it.
 * @see CreateProductCommand The shape, and the same fields without an id.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class UpdateProductCommand
{
    /**
     * @param  UserContext  $context  The caller.
     * @param  string  $id  Base62 id of the product to replace.
     * @param  string  $name  Its new commercial name.
     * @param  float  $density  Kilograms per litre.
     * @param  RiskClass  $riskClass  Already resolved to the enum.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public UserContext $context,
        public string $id,
        public string $name,
        public float $density,
        public RiskClass $riskClass,
    ) {
    }
}
