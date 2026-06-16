<?php

namespace Shared\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Shared\Config\ServerConfigLogLevel;

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