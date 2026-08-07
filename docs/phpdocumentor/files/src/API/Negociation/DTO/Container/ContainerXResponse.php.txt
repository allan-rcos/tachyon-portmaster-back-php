<?php

/**
 * Container Response Message.
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

use Domain\Enums\ContainerStatus;

/**
 * A container, alone or as a row of a page.
 *
 * @see ContainerXResponseFactory What renders this onto the wire.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class ContainerXResponse
{
    /**
     * @param  ?string  $id  Base62 identifier.
     * @param  ?string  $code  The code painted on the container.
     * @param  float  $currentWeight  What it holds right now, in tonnes.
     * @param  float  $maxCapacity  What it can hold, in tonnes.
     * @param  ContainerStatus  $status  Where it is in its lifecycle.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ?string $id = null,
        public ?string $code = null,
        public float $currentWeight = 0.0,
        public float $maxCapacity = 0.0,
        public ContainerStatus $status = ContainerStatus::Empty,
    ) {
    }
}
