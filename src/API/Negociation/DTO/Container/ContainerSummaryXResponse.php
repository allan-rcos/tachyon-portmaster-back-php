<?php

/**
 * Container Summary Response Message.
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
 * A container with everything the yard screen shows about it at once.
 *
 * @see ContainerSummaryXResponseFactory What renders this onto the wire.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class ContainerSummaryXResponse
{
    /**
     * @param  ?ContainerXResponse  $container  The container itself.
     * @param  list<CargoManifestItemX>  $manifest  What is aboard.
     * @param  list<TelemetryLogItemX>  $recentLogs  What happened to it lately.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ?ContainerXResponse $container = null,
        public array $manifest = [],
        public array $recentLogs = [],
    ) {
    }
}
