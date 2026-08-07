<?php

/**
 * Container Create Request Message.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation\DTO\Container;

/**
 * A container being registered in the yard.
 *
 * @see ContainerCreateXRequestFactory What builds this from a request body.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class ContainerCreateXRequest
{
    /**
     * @param  ?string  $code  The code painted on the container.
     * @param  float  $maxCapacity  How much it holds, in tonnes.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ?string $code = null,
        public float $maxCapacity = 0.0,
    ) {
    }
}
