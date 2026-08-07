<?php

/**
 * Container List Response Message.
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
 * One page of containers.
 *
 * @see ContainerListXResponseFactory What renders this onto the wire.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class ContainerListXResponse
{
    /**
     * @param  list<ContainerXResponse>  $data  The rows of this page.
     * @param  ?string  $nextCursor  Opaque cursor for the next page, null on the last.
     * @param  int  $total  How many rows the whole query matches.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public array $data = [],
        public ?string $nextCursor = null,
        public int $total = 0,
    ) {
    }
}
