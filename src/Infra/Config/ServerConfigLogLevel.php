<?php

declare(strict_types=1);

namespace Infra\Config;

enum ServerConfigLogLevel: string
{
    case DEBUG = 'DEBUG';
    case INFO = 'INFO';
    case WARNING = 'WARNING';
    case ERROR = 'ERROR';
}
