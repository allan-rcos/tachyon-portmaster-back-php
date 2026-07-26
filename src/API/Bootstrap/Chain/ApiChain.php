<?php

declare(strict_types=1);

namespace API\Bootstrap\Chain;

use API\Bootstrap\BootDraft;
use API\Bootstrap\DotEnvVariables;
use API\Bootstrap\EnvSource;
use API\Config\ApiConfig;
use API\Config\ServerConfigEnvironmentEnum;

final class ApiChain extends DotEnvChain
{
    protected function process(EnvSource $env, BootDraft $draft): void
    {
        $defaults = new ApiConfig();

        $draft->api = new ApiConfig(
            host: $env->string(DotEnvVariables::APP_HOST, $defaults->host),
            port: $env->int(DotEnvVariables::APP_PORT, $defaults->port),
            workerNum: $env->int(DotEnvVariables::APP_WORKER_NUM, $defaults->workerNum),
            environment: $env->enum(
                DotEnvVariables::ENVIRONMENT,
                ServerConfigEnvironmentEnum::class,
                $defaults->environment,
            ),
        );
    }
}
