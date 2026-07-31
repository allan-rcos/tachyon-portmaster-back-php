<?php

/**
 * Monolog Adapter.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra\Logging;

use Monolog\Logger;
use OpenSwoole\Coroutine;

/**
 * {@see ILogger} over Monolog.
 *
 * The only class in the codebase that knows Monolog exists. Each of the four
 * levels maps to its Monolog equivalent, `warn` to `warning`.
 *
 * **Every line is merged with the current coroutine's metadata** before it is
 * written, which is how a request id set once at the edge reaches lines written
 * deep inside the request without being threaded through. The call's own context
 * wins on a key collision, so a caller can always override what the request set.
 *
 * Readonly, and {@see withChannel()} returns a new instance — the object is
 * safe to share across coroutines. The mutable part lives in the coroutine
 * context, where it is already isolated per request.
 *
 * @see ILogger The contract this implements.
 * @see MonologFactory What builds one.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
readonly class MonologAdapter implements ILogger
{
    /**
     * @param  Logger  $monolog  Already carrying its handler and formatter; the
     *                           adapter configures nothing.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private Logger $monolog
    ) {
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function debug(string $message, array $context = []): void
    {
        $this->monolog->debug($message,
            $this->appendCoroutineContext($context));
    }

    /**
     * Merges the current coroutine's metadata under the call's own context.
     *
     * The call's keys are merged second and therefore win, so per-line detail
     * can override what the request set. Outside a coroutine — or without the
     * extension loaded — the context passes through untouched, which is what
     * lets the same logger work in a script or a test.
     *
     * @param  array<string, mixed>  $context  What the caller passed.
     * @return array<string, mixed> The context actually written.
     *
     * @copyright 2026 Tachyon
     */
    private function appendCoroutineContext(array $context): array
    {
        $coroutineId = Coroutine::getCid();
        if (!extension_loaded('openswoole') || $coroutineId === -1) {
            return $context;
        }

        $coroutineContext = Coroutine::getContext($coroutineId);

        if (isset($coroutineContext['log_metadata']) && is_array($coroutineContext['log_metadata'])) {
            return array_merge($coroutineContext['log_metadata'], $context);
        }

        return $context;
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function info(string $message, array $context = []): void
    {
        $this->monolog->info($message, $this->appendCoroutineContext($context));
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function error(string $message, array $context = []): void
    {
        $this->monolog->error($message,
            $this->appendCoroutineContext($context));
    }

    /**
     * A new adapter over the same Monolog logger renamed to the given channel.
     *
     * Monolog's `withName()` clones rather than renaming, so the caller's logger
     * keeps its own channel.
     *
     * @param  string  $name  The channel.
     * @return ILogger A logger on that channel.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function withChannel(string $name): ILogger
    {
        return new self($this->monolog->withName($name));
    }

    /**
     * Files a key and value against the current coroutine, for every line the
     * request goes on to write.
     *
     * Writes nothing on the adapter itself, which is why this is possible on a
     * readonly, shared object. Existing metadata is merged rather than replaced,
     * so two callers setting different keys both survive.
     *
     * Outside a coroutine there is nowhere to file it: the call warns and
     * returns, deliberately not failing, since logging must never break a
     * caller.
     *
     * @param  string  $key  What to file it under.
     * @param  string  $value  The value.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function setContext(string $key, string $value): void
    {
        $coroutineId = Coroutine::getCid();
        if (!extension_loaded('openswoole') || $coroutineId === -1) {
            $this->warn(
                "openswoole extension not loaded, failed to add context",
                ['key' => $key, 'value' => $value],
            );
            return;
        }

        $coroutineContext = Coroutine::getContext($coroutineId);

        $existing = $coroutineContext['log_metadata'] ?? [];
        $coroutineContext['log_metadata'] = array_merge(
            is_array($existing) ? $existing : [],
            [$key => $value],
        );
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function warn(string $message, array $context = []): void
    {
        $this->monolog->warning($message,
            $this->appendCoroutineContext($context));
    }
}
