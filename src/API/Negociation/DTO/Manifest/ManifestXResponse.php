<?php

/**
 * Manifest Response Message.
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

use API\Negociation\DTO\Container\ContainerXResponse;

/**
 * What a load or unload answers with: a word for the operator and the container
 * as it now stands.
 *
 * @see ManifestXResponseFactory What renders this onto the wire.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class ManifestXResponse
{
    /**
     * @param  ?string  $message  Human-readable confirmation.
     * @param  ?ContainerXResponse  $container  The container after the movement.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ?string $message = null,
        public ?ContainerXResponse $container = null,
    ) {
    }
}
