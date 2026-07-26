<?php

declare(strict_types=1);

namespace Infra\Database\Pool\Interno;

use OpenSwoole\Core\Coroutine\Client\PDOConfig;

/**
 * Builds a {@see PDOConfig} for the OpenSwoole coroutine PDO client.
 *
 * Kept separate from the pool so the connection parameters can be assembled
 * from environment/config in one place and handed to the DI container.
 */
final class PDOConfigFactory
{
    /**
     * @param  array<string, mixed>  $options
     */
    public static function mysql(
        string $host,
        int $port,
        string $dbname,
        string $username,
        string $password,
        string $charset = 'utf8mb4',
        array $options = [],
    ): PDOConfig {
        return (new PDOConfig())
            ->withDriver(PDOConfig::DRIVER_MYSQL)
            ->withHost($host)
            ->withPort($port)
            ->withDbname($dbname)
            ->withUsername($username)
            ->withPassword($password)
            ->withCharset($charset)
            ->withOptions($options);
    }
}
