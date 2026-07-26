<?php

declare(strict_types=1);

namespace API\Bootstrap\Chain;

use API\Bootstrap\BootDraft;
use API\Bootstrap\DotEnvVariables;
use API\Bootstrap\EnvSource;
use Domain\Config\DomainConfig;

final class DomainChain extends DotEnvChain
{
    protected function process(EnvSource $env, BootDraft $draft): void
    {
        $defaults = new DomainConfig();

        $draft->domain = new DomainConfig(
            snowflakeEpoch: $env->int(DotEnvVariables::SNOWFLAKE_EPOCH, $defaults->snowflakeEpoch),
            snowflakeClusterId: $env->int(DotEnvVariables::SNOWFLAKE_MACHINE_ID, $defaults->snowflakeClusterId),
        );
    }
}
