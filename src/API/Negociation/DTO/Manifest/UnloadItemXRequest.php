<?php

/**
 * Unload Item Request Message.
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
 * Cargo coming out of a container.
 *
 * @see UnloadItemXRequestFactory What builds this from a request body.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class UnloadItemXRequest
{
    /**
     * @param  ?string  $containerId  Base62 id of the container being unloaded.
     * @param  ?string  $productId  Base62 id of the product coming out.
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
