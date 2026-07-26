<?php

declare(strict_types=1);

namespace Infra\Logging;

use Infra\Config\ServerConfigLogLevel;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class MonologFactory
{
    public static function create(
        string $name = 'swoole-api',
        ServerConfigLogLevel $level = ServerConfigLogLevel::INFO
    ): ILogger {
        $logger = new Logger($name);

        $handler = new StreamHandler('php://stdout', $level->value);

        $handler->setFormatter(new JsonFormatter());

        $logger->pushHandler($handler);

        return new MonologAdapter($logger);
    }
}
