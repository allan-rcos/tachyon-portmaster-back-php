<?php

/**
 * Project Info DTO Module.
 *
 * Module wrapping basic internal status of the application logic layout.
 *
 * @category Api\DTO
 *
 * @since 0.0.1 File creation.
 *
 * @version 0.0.1
 *
 * @license {@link https://www.gnu.org/licenses/gpl-3.0.pt-br.html GPL-3}
 * @copyright 2026 Ricardo Állan Costa
 * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
 *
 * @filesource
 */

namespace API\DTO;

/**
 * Data Transfer Object representing project metadata and runtime information.
 *
 * Provides a serializable standard layer conveying context payload layout variables.
 *
 * @license {@link https://www.gnu.org/licenses/gpl-3.0.pt-br.html GPL-3}
 * @copyright 2026 Ricardo Állan Costa
 * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
 *
 * @see JsonSerializable
 *
 * @since 0.0.1 File creation.
 *
 * @version 0.0.1
 */
readonly class ProjectInfoDTO implements \JsonSerializable
{
    /**
     * ProjectInfoDTO constructor.
     *
     * Bootstraps standard configuration values for API endpoints outputs.
     *
     * @param  string  $name  The name of the project.
     * @param  string  $version  The current version.
     * @param  string  $environment  The runtime environment.
     * @param  string  $runtime  Information about the PHP runtime.
     * @param  float  $memory_usage_mb  Overall memory usage of the process in MB.
     * @copyright 2026 Ricardo Állan Costa
     *
     * @since 0.0.1 File creation.
     *
     * @version 0.0.1
     *
     * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
     */
    public function __construct(
        public string $name,
        public string $version,
        public string $environment,
        public string $runtime,
        public float $memory_usage_mb
    ) {
    }

    /**
     * Define exatamente o mapa de serialização para o json_encode
     *
     * @return array<string, mixed> The serialized array notation.
     * @copyright 2026 Ricardo Állan Costa
     *
     * @since 0.0.1 File creation.
     *
     * @version 0.0.1
     *
     * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
     */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'environment' => $this->environment,
            'runtime' => $this->runtime,
            'memory_usage_mb' => $this->memory_usage_mb
        ];
    }
}