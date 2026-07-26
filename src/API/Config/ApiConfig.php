<?php

declare(strict_types=1);

namespace API\Config;

/**
 * API (presentation) configuration value object.
 *
 * Carries the HTTP server runtime settings owned by the presentation layer. The
 * inner-layer settings (database, jwt, logging, snowflake) live in their own VOs
 * and are aggregated by {@see BootConfig} at bootstrap.
 */
readonly class ApiConfig
{
    public function __construct(
        public string $host = '127.0.0.1',
        public int $port = 9501,
        public int $workerNum = 4,
        public ServerConfigEnvironmentEnum $environment = ServerConfigEnvironmentEnum::DEVELOPMENT,
    ) {
    }
}
