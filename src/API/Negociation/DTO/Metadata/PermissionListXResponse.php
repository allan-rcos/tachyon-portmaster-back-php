<?php

/**
 * Permission List Response Message.
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
 * The whole permission catalogue, as the roles screen needs it.
 *
 * @see PermissionListXResponseFactory What renders this onto the wire.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class PermissionListXResponse
{
    /**
     * @param  list<MetadataItemXResponse>  $data  Every permission the workers declared.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public array $data = [],
    ) {
    }
}
