<?php

declare(strict_types=1);

namespace API\Bootstrap\Chain;

use API\Bootstrap\BootDraft;
use API\Bootstrap\DotEnvVariables;
use API\Bootstrap\EnvSource;
use Infra\Config\DatabaseConfig;

final class DatabaseChain extends DotEnvChain
{
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
        );
    }
}
