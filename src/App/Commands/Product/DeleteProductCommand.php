<?php

/**
 * Delete Product Command.
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

/**
 * Removes one product by id.
 *
 * Follows the command shape documented on {@see CreateProductCommand}, reduced
 * to the caller and one identifier.
 *
 * @see \App\Services\IDeleteProductUseCase What consumes it.
 * @see CreateProductCommand The shape.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class DeleteProductCommand
{
    /**
     * @param  UserContext  $context  The caller.
     * @param  string  $id  Base62 id of the product to remove.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public UserContext $context,
        public string $id,
    ) {
    }
}
