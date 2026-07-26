<?php

declare(strict_types=1);

namespace API\Bootstrap\Chain;

use API\Bootstrap\BootDraft;
use API\Bootstrap\EnvSource;

/**
 * One link of the environment-reading chain.
 *
 * `DotEnvStarter` used to be a single class with a setter, a field and a
 * validation branch per variable — the kind of class that only grows. Each
 * config group is now its own link: a new variable means editing (or adding) one
 * small link instead of widening a god object.
 *
 * The whole chain runs once at startup and is dropped immediately afterwards, so
 * the extra objects cost nothing for the process's lifetime.
 */
abstract class DotEnvChain
{
    private ?DotEnvChain $next = null;

    /**
     * Appends a link and returns **it**, so a chain reads as
     * `$first->then($second)->then($third)` while `$first` stays the head.
     */
    final public function then(self $next): self
    {
        $this->next = $next;

        return $next;
    }

    /**
     * Runs this link, then the rest of the chain.
     *
     * Every link always runs: collecting all the invalid variables in one pass
     * is the point, so a failure here never short-circuits the chain.
     */
    final public function handle(EnvSource $env, BootDraft $draft): void
    {
        $this->process($env, $draft);
        $this->next?->handle($env, $draft);
    }

    abstract protected function process(EnvSource $env, BootDraft $draft): void;
}
