<?php

declare(strict_types=1);

namespace API\Bootstrap;

/**
 * Enum outlining the expected environment variables for the application.
 *
 * Owned by the presentation bootstrap ({@see DotEnvStarter}), the composition
 * root that reads the environment and builds the per-layer config VOs. Each case
 * is consumed by exactly one {@see \API\Bootstrap\Chain\DotEnvChain} link.
 */
enum DotEnvVariables: string
{
    case APP_HOST = 'APP_HOST';
    case APP_PORT = 'APP_PORT';
    case APP_WORKER_NUM = 'APP_WORKER_NUM';
    case SNOWFLAKE_EPOCH = 'SNOWFLAKE_EPOCH';
    case SNOWFLAKE_MACHINE_ID = 'SNOWFLAKE_MACHINE_ID';
    case ENVIRONMENT = 'ENVIRONMENT';
    case LOG_LEVEL = 'LOG_LEVEL';
    case APP_DB_HOST = 'APP_DB_HOST';
    case APP_DB_PORT = 'APP_DB_PORT';
    case APP_DB_NAME = 'APP_DB_NAME';
    case APP_DB_USER = 'APP_DB_USER';
    case APP_DB_PASSWORD = 'APP_DB_PASSWORD';
    case APP_DB_CHARSET = 'APP_DB_CHARSET';
    case APP_DB_POOL_SIZE = 'APP_DB_POOL_SIZE';
    case APP_DB_POOL_TIMEOUT = 'APP_DB_POOL_TIMEOUT';
    case APP_DB_MAX_LEASE = 'APP_DB_MAX_LEASE';
    case APP_DB_MAX_IDLE = 'APP_DB_MAX_IDLE';
    case APP_JWT_SECRET = 'APP_JWT_SECRET';
    case APP_JWT_TTL = 'APP_JWT_TTL';
    case APP_JWT_ISSUER = 'APP_JWT_ISSUER';
    case APP_JWT_COOKIE_NAME = 'APP_JWT_COOKIE_NAME';
    case APP_JWT_COOKIE_SECURE = 'APP_JWT_COOKIE_SECURE';
    case APP_REFRESH_COOKIE_NAME = 'APP_REFRESH_COOKIE_NAME';
    case APP_REFRESH_TTL = 'APP_REFRESH_TTL';
}
