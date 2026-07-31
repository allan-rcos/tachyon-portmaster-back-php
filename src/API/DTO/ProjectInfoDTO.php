<?php

/**
 * Project Info DTO.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

namespace API\DTO;

/**
 * Data Transfer Object representing project metadata and runtime information.
 *
 * Provides a serializable standard layer conveying context payload layout variables.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @see JsonSerializable
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
     * @copyright 2026 Tachyon
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
     * @copyright 2026 Tachyon
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