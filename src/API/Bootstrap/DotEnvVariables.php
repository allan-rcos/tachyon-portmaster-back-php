<?php

/**
 * DotEnv Variables Enum.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Bootstrap;

/**
 * Enum outlining the expected environment variables for the application.
 *
 * Owned by the presentation bootstrap ({@see DotEnvStarter}), the composition
 * root that reads the environment and builds the per-layer config VOs. Each case
 * is consumed by exactly one {@see \API\Bootstrap\Chain\DotEnvChain} link.
 *
 * A case listed here is not a required variable: every one has a default in the
 * config VO it feeds, so an unset — or empty — variable falls back rather than
 * failing the boot. `APP_JWT_SECRET` is the single exception, and `JwtChain`
 * rejects it explicitly.
 *
 * @see EnvSource What reads a case's value and types it.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
enum DotEnvVariables: string
{
    /**
     * Address the HTTP server binds to.
     */
    case APP_HOST = 'APP_HOST';

    /**
     * Port the HTTP server listens on.
     */
    case APP_PORT = 'APP_PORT';

    /**
     * How many OpenSwoole worker processes to fork.
     */
    case APP_WORKER_NUM = 'APP_WORKER_NUM';

    /**
     * Milliseconds the snowflake timestamps count from.
     */
    case SNOWFLAKE_EPOCH = 'SNOWFLAKE_EPOCH';

    /**
     * This node's cluster id, the part of a snowflake that keeps two servers
     * from minting the same identifier.
     */
    case SNOWFLAKE_MACHINE_ID = 'SNOWFLAKE_MACHINE_ID';

    /**
     * Which {@see \API\Config\ServerConfigEnvironmentEnum} variant to boot as.
     */
    case ENVIRONMENT = 'ENVIRONMENT';

    /**
     * Verbosity floor, one of {@see \Infra\Config\ServerConfigLogLevel}.
     */
    case LOG_LEVEL = 'LOG_LEVEL';

    /**
     * Database host.
     */
    case APP_DB_HOST = 'APP_DB_HOST';

    /**
     * Database port.
     */
    case APP_DB_PORT = 'APP_DB_PORT';

    /**
     * Schema to connect to.
     */
    case APP_DB_NAME = 'APP_DB_NAME';

    /**
     * Connecting user.
     */
    case APP_DB_USER = 'APP_DB_USER';

    /**
     * Their password.
     */
    case APP_DB_PASSWORD = 'APP_DB_PASSWORD';

    /**
     * Connection charset.
     */
    case APP_DB_CHARSET = 'APP_DB_CHARSET';

    /**
     * How many connections the pool may open, per worker.
     */
    case APP_DB_POOL_SIZE = 'APP_DB_POOL_SIZE';

    /**
     * Seconds a borrower waits for a free connection before the lease fails.
     */
    case APP_DB_POOL_TIMEOUT = 'APP_DB_POOL_TIMEOUT';

    /**
     * Intended ceiling on how long one lease is held. Read into
     * {@see \Infra\Config\DatabaseConfig} but not currently enforced.
     */
    case APP_DB_MAX_LEASE = 'APP_DB_MAX_LEASE';

    /**
     * Intended ceiling on how long an unused connection sits in the pool. Also
     * unread at present.
     */
    case APP_DB_MAX_IDLE = 'APP_DB_MAX_IDLE';

    /**
     * How the database connection is protected, one of
     * {@see \Infra\Config\DatabaseSslMode}. Unset means no TLS.
     */
    case APP_DB_SSL_MODE = 'APP_DB_SSL_MODE';

    /**
     * Path to the CA bundle the server certificate is validated against. Only
     * read when the mode is `verify_ca`.
     */
    case APP_DB_SSL_CA = 'APP_DB_SSL_CA';

    /**
     * Whether the certificate's common name has to match the host connected
     * to. Also only read when the mode is `verify_ca`.
     */
    case APP_DB_SSL_VERIFY_CN = 'APP_DB_SSL_VERIFY_CN';

    /**
     * Signing key for the session tokens. The only variable the boot insists
     * on: {@see \API\Bootstrap\Chain\JwtChain} refuses anything shorter than 32
     * bytes.
     */
    case APP_JWT_SECRET = 'APP_JWT_SECRET';

    /**
     * Seconds an access token stays valid.
     */
    case APP_JWT_TTL = 'APP_JWT_TTL';

    /**
     * Value of the `iss` claim, checked on verify.
     */
    case APP_JWT_ISSUER = 'APP_JWT_ISSUER';

    /**
     * Cookie the access token travels in.
     */
    case APP_JWT_COOKIE_NAME = 'APP_JWT_COOKIE_NAME';

    /**
     * Whether both auth cookies carry `Secure`, confining them to HTTPS.
     */
    case APP_JWT_COOKIE_SECURE = 'APP_JWT_COOKIE_SECURE';

    /**
     * Cookie the refresh token travels in.
     */
    case APP_REFRESH_COOKIE_NAME = 'APP_REFRESH_COOKIE_NAME';

    /**
     * Seconds a refresh token stays valid.
     */
    case APP_REFRESH_TTL = 'APP_REFRESH_TTL';
}
