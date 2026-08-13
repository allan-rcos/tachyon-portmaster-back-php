<?php

/**
 * Load Item Command.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App\Commands\Manifest;

use App\Context\UserContext;

/**
 * Loads a quantity of a product into a container.
 *
 * Follows the command shape documented on
 * {@see \App\Commands\Product\CreateProductCommand}.
 *
 * Carries a quantity, not a weight: what that quantity weighs follows from the
 * product's density, and letting a caller assert it independently would allow a
 * manifest whose arithmetic does not hold. Whether the container can take it —
 * capacity, status, compatibility — is the table module's to decide.
 *
 * @see \App\Services\ILoadItemUseCase What consumes it.
 * @see UnloadItemCommand The inverse.
 * @see \App\Commands\Product\CreateProductCommand The command shape.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class LoadItemCommand
{
    /**
     * @param  UserContext  $context  The caller.
     * @param  string  $containerId  Base62 id of the container to load into.
     * @param  string  $productId  Base62 id of what is being loaded.
     * @param  float  $quantity  How many units to add to whatever the container
     *                           already carries.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public UserContext $context,
        public string $containerId,
        public string $productId,
        public float $quantity,
    ) {
    }
}
