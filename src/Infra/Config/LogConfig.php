<?php

/**
 * Log Config.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra\Config;

/**
 * Logging configuration value object.
 *
 * Carries the log level the infra logger factory ({@see \Infra\Logging\MonologFactory})
 * uses when building the application logger.
 *
 * One field today, and a value object rather than a bare enum so that a second
 * logging setting can be added without changing every signature that passes it.
 *
 * @see ServerConfigLogLevel The level itself.
 * @see \Infra\Logging\MonologFactory What consumes it.
 * @see DatabaseConfig The same pattern, for the database.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
readonly class LogConfig
{
    /**
     * @param  ServerConfigLogLevel  $level  The floor below which lines are
     *                                       dropped; defaults to informational,
     *                                       so debug output is opt-in.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ServerConfigLogLevel $level = ServerConfigLogLevel::INFO,
    ) {
    }
}
