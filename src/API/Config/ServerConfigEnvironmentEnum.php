<?php

declare(strict_types=1);

namespace API\Config;

/**
 * Runtime environment variants for the server.
 *
 * Drives environment-specific bootstrap decisions in {@see \API\main} (e.g.
 * enabling opcache/JIT-friendly paths in production).
 */
enum ServerConfigEnvironmentEnum: string
{
    case DEVELOPMENT = 'DEV';
    case PRODUCTION = 'PROD';
}
