<?php

/**
 * Server Configuration Object.
 *
 * Module outlining the mapped infrastructure properties setups.
 *
 * @category Shared\Config
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

namespace Shared\Config;

/**
 * Value object representing configuration extracted for the server execution.
 *
 * It is structured to transport strictly internal initialization options bound values.
 *
 * @license {@link https://www.gnu.org/licenses/gpl-3.0.pt-br.html GPL-3}
 * @copyright 2026 Ricardo Állan Costa
 * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
 *
 * @since 0.0.1 File creation.
 *
 * @version 0.0.1
 */
readonly class ServerConfig
{
    /**
     * ServerConfig constructor.
     *
     * @param  string  $host  The IP address or hostname to bind.
     * @param  int  $port  The port number to bind the server on.
     * @param  int  $snowflakeEpoch  Custom epoch for Snowflake ID generation.
     * @param  int  $snowflakeClusterId  Cluster ID for Snowflake ID generation, allowing for distributed ID generation across multiple instances.
     * @param  int  $workerNum  Number of Swoole worker processes.
     * @param  ServerConfigEnvironmentEnum  $environment  Environment execution context.
     * @param  ServerConfigLogLevel  $logLevel  Log level for server output.
     * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
     * @copyright 2026 Ricardo Állan Costa
     *
     * @since 0.0.1 File creation.
     *
     * @version 0.0.1
     *
     */
    private function __construct(
        public string $host,
        public int $port,
        public int $snowflakeEpoch,
        public int $snowflakeClusterId,
        public int $workerNum,
        public string
        public ServerConfigEnvironmentEnum $environment,
        public ServerConfigLogLevel $logLevel,
    ) {
    }

    /**
     * Replaces standard instantiation constructor representing a factory.
     *
     * @param  string|null  $host  Target IP to bind.
     * @param  int|null  $port  Target port to bind.
     * @param  int|null  $snowflakeEpoch
     * @param  int|null  $snowflakeClusterId
     * @param  int|null  $workerNum  Process worker count to boost throughput.
     * @param  ServerConfigEnvironmentEnum|null  $environment  App environment domain context.
     * @param  ServerConfigLogLevel|null  $logLevel  Log level for server output.
     * @return self
     * @copyright 2026 Ricardo Állan Costa
     *
     * @since 0.0.1 File creation.
     *
     * @version 0.0.1
     *
     * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
     */
    public static function new(
        ?string $host = '127.0.0.1',
        ?int $port = 9501,
        ?int $snowflakeEpoch = 1704067200000, // January 1, 2024
        ?int $snowflakeClusterId = 1,
        ?int $workerNum = 4,
        ?ServerConfigEnvironmentEnum $environment = ServerConfigEnvironmentEnum::DEVELOPMENT,
        ?ServerConfigLogLevel $logLevel = ServerConfigLogLevel::INFO,
    ): self {
        return new self(
            host: $host ?? '127.0.0.1',
            port: $port ?? 9501,
            snowflakeEpoch: $snowflakeEpoch ?? 1704067200000,
            snowflakeClusterId: $snowflakeClusterId ?? 1,
            workerNum: $workerNum ?? 4,
            environment: $environment ?? ServerConfigEnvironmentEnum::DEVELOPMENT,
            logLevel: $logLevel ?? ServerConfigLogLevel::INFO
        );
    }
}