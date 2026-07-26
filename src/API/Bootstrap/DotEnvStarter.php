<?php

declare(strict_types=1);

namespace API\Bootstrap;

use API\Bootstrap\Chain\ApiChain;
use API\Bootstrap\Chain\DatabaseChain;
use API\Bootstrap\Chain\DomainChain;
use API\Bootstrap\Chain\JwtChain;
use API\Bootstrap\Chain\LogChain;
use API\Config\BootConfig;
use Dotenv\Dotenv;
use Shared\Exceptions\LeafContext;

/**
 * Reads and validates the environment, then builds the per-layer config VOs
 * bundled in a {@see BootConfig}.
 *
 * This is the composition root's env step: it lives in the presentation layer
 * (which owns `main`) and produces the config each layer's register consumes, so
 * no inner-layer service ever touches `$_ENV`.
 *
 * The work is delegated to a chain of {@see \API\Bootstrap\Chain\DotEnvChain}
 * links, one per config group. This class only assembles the chain and reports
 * the outcome — adding a variable never touches it.
 */
final class DotEnvStarter
{
    /**
     * Loads the environment from the given directory and builds the boot config,
     * or returns a {@see LeafContext} listing **every** invalid variable — the
     * chain runs to completion rather than stopping at the first problem.
     */
    public static function startFromEnv(string $dir): LeafContext|BootConfig
    {
        // safeLoad(): a .env file is a development convenience. In a container
        // the configuration arrives as real environment variables, so a missing
        // file must not be fatal — the values are read from $_ENV either way.
        Dotenv::createImmutable($dir)->safeLoad();

        $env = new EnvSource();
        $draft = new BootDraft();

        self::chain()->handle($env, $draft);

        if ($env->hasErrors()) {
            return new LeafContext(
                message: 'Invalid environment variables',
                details: $env->errors(),
            );
        }

        return $draft->toBootConfig();
    }

    /**
     * The head of the chain. Order is irrelevant — the links are independent and
     * each fills its own slot — so new groups can simply be appended.
     */
    private static function chain(): Chain\DotEnvChain
    {
        $head = new ApiChain();

        $head->then(new DomainChain())
            ->then(new DatabaseChain())
            ->then(new JwtChain())
            ->then(new LogChain());

        return $head;
    }
}
