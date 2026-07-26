<?php

declare(strict_types=1);

namespace Infra\Config;

/**
 * Logging configuration value object.
 *
 * Carries the log level the infra logger factory ({@see \Infra\Logging\MonologFactory})
 * uses when building the application logger.
 */
readonly class LogConfig
{
    public function __construct(
        public ServerConfigLogLevel $level = ServerConfigLogLevel::INFO,
    ) {
    }
}
