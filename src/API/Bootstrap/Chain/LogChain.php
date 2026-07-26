<?php

declare(strict_types=1);

namespace API\Bootstrap\Chain;

use API\Bootstrap\BootDraft;
use API\Bootstrap\DotEnvVariables;
use API\Bootstrap\EnvSource;
use Infra\Config\LogConfig;
use Infra\Config\ServerConfigLogLevel;

final class LogChain extends DotEnvChain
{
    protected function process(EnvSource $env, BootDraft $draft): void
    {
        $defaults = new LogConfig();

        $draft->log = new LogConfig(
            level: $env->enum(DotEnvVariables::LOG_LEVEL, ServerConfigLogLevel::class, $defaults->level),
        );
    }
}
