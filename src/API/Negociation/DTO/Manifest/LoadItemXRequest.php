<?php

/**
 * Load Item Request Message.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation\DTO\Manifest;

/**
 * Cargo going into a container.
 *
 * @see LoadItemXRequestFactory What builds this from a request body.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class LoadItemXRequest
{
    /**
     * @param  ?string  $containerId  Base62 id of the container being loaded.
     * @param  ?string  $productId  Base62 id of the product going in.
     * @param  float  $quantity  How much of it, in tonnes.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ?string $containerId = null,
        public ?string $productId = null,
        public float $quantity = 0.0,
    ) {
    }
}
