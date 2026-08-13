<?php

/**
 * DotEnv Chain.
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
 *
 * @see \API\Bootstrap\DotEnvStarter What assembles and runs the chain.
 * @uses EnvSource Reads and validates each variable.
 * @uses BootDraft The accumulator a link writes its group into.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
abstract class DotEnvChain
{
    /**
     * @var DotEnvChain|null The next link, or null at the tail.
     */
    private ?DotEnvChain $next = null;

    /**
     * Appends a link.
     *
     * Returns **it**, not `$this`, so a chain reads as
     * `$first->then($second)->then($third)` while `$first` stays the head.
     *
     * @param  self  $next  Link to run after this one.
     * @return self The link just appended.
     *
     * @copyright 2026 Tachyon
     *
     * @api
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
     *
     * @param  EnvSource  $env  Reader over the loaded environment.
     * @param  BootDraft  $draft  Accumulator each link writes its group into.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    final public function handle(EnvSource $env, BootDraft $draft): void
    {
        $this->process($env, $draft);
        $this->next?->handle($env, $draft);
    }

    /**
     * Reads this link's variables and fills its slot on the draft.
     *
     * @param  EnvSource  $env  Reader over the loaded environment.
     * @param  BootDraft  $draft  Accumulator to write this link's group into.
     *
     * @copyright 2026 Tachyon
     */
    abstract protected function process(EnvSource $env, BootDraft $draft): void;
}
