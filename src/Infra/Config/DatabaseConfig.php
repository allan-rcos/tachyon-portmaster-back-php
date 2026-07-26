<?php

declare(strict_types=1);

namespace Infra\Config;

/**
 * Database connection and pool settings.
 *
 * Resolved from the environment during bootstrap (see
 * {@see \API\Bootstrap\DotEnvStarter}) and handed to the infra layer through
 * {@see \Infra\InfraRegister}, so no service reads the environment directly.
 */
readonly class DatabaseConfig
{
    public function __construct(
        public string $host = '127.0.0.1',
        public int $port = 3306,
        public string $name = 'app',
        public string $user = 'root',
        public string $password = '',
        public string $charset = 'utf8mb4',
        public int $poolSize = 16,
        public float $poolTimeout = 5.0,
        public float $maxLeaseTime = 30.0,
        public float $maxIdleTime = 60.0,
    ) {
    }
}
