<?php

/**
 * Metadata Item Response Message.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation\DTO\Metadata;

/**
 * One entry of a registry catalogue — a permission, today.
 *
 * @see MetadataItemXResponseFactory What renders this onto the wire.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class MetadataItemXResponse
{
    /**
     * @param  int  $id  The registry's numeric id.
     * @param  string  $slug  The stable name callers match on.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public int $id = 0,
        public string $slug = '',
    ) {
    }
}
