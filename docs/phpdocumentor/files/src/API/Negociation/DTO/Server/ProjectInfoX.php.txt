<?php

/**
 * Project Info Message.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation\DTO\Server;

/**
 * What the process says about itself on `GET /info`.
 *
 * @see ProjectInfoXFactory What renders this onto the wire.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class ProjectInfoX
{
    /**
     * @param  ?string  $name  The name of the project.
     * @param  ?string  $version  The version being served.
     * @param  ?string  $environment  The runtime environment.
     * @param  ?string  $runtime  Information about the PHP runtime.
     * @param  float  $memoryUsageMb  Overall memory usage of the process in MB.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ?string $name = null,
        public ?string $version = null,
        public ?string $environment = null,
        public ?string $runtime = null,
        public float $memoryUsageMb = 0.0,
    ) {
    }
}
