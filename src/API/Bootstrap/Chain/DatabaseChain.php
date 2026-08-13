<?php

/**
 * Database Chain.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Bootstrap\Chain;

use API\Bootstrap\BootDraft;
use API\Bootstrap\DotEnvVariables;
use API\Bootstrap\EnvSource;
use Infra\Config\DatabaseConfig;
use Infra\Config\DatabaseSslMode;

/**
 * Reads the connection and pool variables into a {@see DatabaseConfig}.
 *
 * @see ApiChain The link shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final class DatabaseChain extends DotEnvChain
{
    /**
     * Fills the draft's `database` slot from the thirteen `APP_DB_*` variables.
     *
     * @param  EnvSource  $env  Reader over the loaded environment.
     * @param  BootDraft  $draft  Accumulator to write the database group into.
     *
     * @copyright 2026 Tachyon
     */
    protected function process(EnvSource $env, BootDraft $draft): void
    {
        $defaults = new DatabaseConfig();

        $draft->database = new DatabaseConfig(
            host: $env->string(DotEnvVariables::APP_DB_HOST, $defaults->host),
            port: $env->int(DotEnvVariables::APP_DB_PORT, $defaults->port),
            name: $env->string(DotEnvVariables::APP_DB_NAME, $defaults->name),
            user: $env->string(DotEnvVariables::APP_DB_USER, $defaults->user),
            password: $env->string(DotEnvVariables::APP_DB_PASSWORD, $defaults->password),
            charset: $env->string(DotEnvVariables::APP_DB_CHARSET, $defaults->charset),
            poolSize: $env->int(DotEnvVariables::APP_DB_POOL_SIZE, $defaults->poolSize),
            poolTimeout: $env->float(DotEnvVariables::APP_DB_POOL_TIMEOUT, $defaults->poolTimeout),
            maxLeaseTime: $env->float(DotEnvVariables::APP_DB_MAX_LEASE, $defaults->maxLeaseTime),
            maxIdleTime: $env->float(DotEnvVariables::APP_DB_MAX_IDLE, $defaults->maxIdleTime),
            sslMode: $env->enum(
                DotEnvVariables::APP_DB_SSL_MODE,
                DatabaseSslMode::class,
                $defaults->sslMode,
            ),
            sslCa: $env->string(DotEnvVariables::APP_DB_SSL_CA, $defaults->sslCa),
            sslVerifyCn: $env->bool(DotEnvVariables::APP_DB_SSL_VERIFY_CN, $defaults->sslVerifyCn),
        );
    }
}
