<?php

/**
 * Coroutine Meta Event Stack.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App\Events\Interno;

use App\Events\IMetaEventStack;
use App\Events\MetaEvent;
use OpenSwoole\Coroutine;

/**
 * {@see IMetaEventStack} over the coroutine context.
 *
 * **The events are not a field on this object.** The graph is built once per
 * worker, so a field would be shared by every request that worker has in flight
 * — and under `enable_coroutine` that is several at once, which would let one
 * request's cache hit mark another's response. The coroutine context is
 * per-request and discarded with the coroutine, the same mechanism
 * {@see \API\Http\RequestAttributes} uses.
 *
 * It is read straight in each method rather than through a shared helper,
 * matching that class: `Coroutine::getContext()` ships no stub, so its type is
 * only ever as narrow as the guards around it.
 *
 * Outside a coroutine — a unit test, a CLI run — every method is inert:
 * {@see emit()} discards and {@see captured()} answers false. Nothing above
 * depends on the events for correctness, so silence is the right failure.
 *
 * @see IMetaEventStack The contract this implements.
 * @see docs/adr/0010-read-cache-in-a-memory-table.md Why the events exist and why they live in the coroutine.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class CoroutineMetaEventStack implements IMetaEventStack
{
    /**
     * @var string Key the set is stored under in the coroutine context.
     *
     * Namespaced away from {@see \API\Http\RequestAttributes}' keys, which share
     * the same context: the two are unrelated and must not be able to collide.
     */
    private const string CONTEXT_KEY = 'meta_events';

    /**
     * Records the event, if there is a request in scope.
     *
     * @param  MetaEvent  $event  What happened.
     *
     * @copyright 2026 Tachyon
     */
    public function emit(MetaEvent $event): void
    {
        $cid = Coroutine::getCid();
        if ($cid <= 0) {
            return;
        }

        $context = Coroutine::getContext($cid);
        if ($context === null) {
            return;
        }

        $events = $context[self::CONTEXT_KEY] ?? [];
        if (!is_array($events)) {
            $events = [];
        }

        $events[$event->value] = true;
        $context[self::CONTEXT_KEY] = $events;
    }

    /**
     * Whether the event was recorded for this request.
     *
     * @param  MetaEvent  $event  What to ask about.
     * @return bool False outside a coroutine, and false before anything emitted.
     *
     * @copyright 2026 Tachyon
     */
    public function captured(MetaEvent $event): bool
    {
        $cid = Coroutine::getCid();
        if ($cid <= 0) {
            return false;
        }

        $context = Coroutine::getContext($cid);
        if ($context === null) {
            return false;
        }

        $events = $context[self::CONTEXT_KEY] ?? null;

        return is_array($events) && isset($events[$event->value]);
    }

    /**
     * Drops the whole set for this request.
     *
     * @copyright 2026 Tachyon
     */
    public function flush(): void
    {
        $cid = Coroutine::getCid();
        if ($cid <= 0) {
            return;
        }

        $context = Coroutine::getContext($cid);
        if ($context !== null) {
            $context[self::CONTEXT_KEY] = [];
        }
    }
}
