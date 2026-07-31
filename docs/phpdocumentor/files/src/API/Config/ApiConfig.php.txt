<?php

/**
 * API Config.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Config;

/**
 * API (presentation) configuration value object.
 *
 * Carries the HTTP server runtime settings owned by the presentation layer. The
 * inner-layer settings (database, jwt, logging, snowflake) live in their own VOs
 * and are aggregated by {@see BootConfig} at bootstrap.
 *
 * Every field has a default, so a missing environment variable yields a working
 * local server rather than a boot failure — and a mistyped variable name is
 * silently the default, not an error.
 *
 * @see BootConfig What carries this alongside the other layers' settings.
 * @see \API\Bootstrap\DotEnvStarter What resolves it from the environment.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
readonly class ApiConfig
{
    /**
     * @param  string  $host  Address the server binds to. The default accepts
     *                        only local connections; a container publishing the
     *                        port needs `0.0.0.0`.
     * @param  int  $port  Port the server listens on.
     * @param  int  $workerNum  How many OpenSwoole worker processes to fork.
     * @param  ServerConfigEnvironmentEnum  $environment  Which runtime variant
     *                                                    the server boots as.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public string $host = '127.0.0.1',
        public int $port = 9501,
        public int $workerNum = 4,
        public ServerConfigEnvironmentEnum $environment = ServerConfigEnvironmentEnum::DEVELOPMENT,
    ) {
    }
}
