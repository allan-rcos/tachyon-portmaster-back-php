<?php

/**
 * Meta Event Stack Contract.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App\Events;

/**
 * Collects the {@see MetaEvent}s reported while one request is answered.
 *
 * A set rather than a log: the only questions asked of it are whether something
 * happened at all, so an event reported twice is indistinguishable from one
 * reported once, and nothing records the order.
 *
 * **The scope is one request.** Two requests being served at the same moment
 * must never see each other's events, which is what an implementation of this
 * has to guarantee and what {@see flush()} exists to make explicit.
 *
 * It runs the other way round from the rest of the application layer: a use case
 * *writes* here and never reads, and the API layer reads and never writes. That
 * is deliberate — a use case that branched on an event would be deciding what to
 * do based on how it had already decided to do it.
 *
 * @see MetaEvent What is reported.
 * @see \App\Events\Interno\CoroutineMetaEventStack The implementation.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IMetaEventStack
{
    /**
     * Reports that the event happened while answering this request.
     *
     * Idempotent, and never fails: reporting is a side note on work that has
     * already succeeded, so nothing a caller does with the result would be
     * right. That is why this returns void where the rest of the layer returns
     * a `Result`.
     *
     * @param  MetaEvent  $event  What happened.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function emit(MetaEvent $event): void;

    /**
     * Whether the event was reported while answering this request.
     *
     * @param  MetaEvent  $event  What to ask about.
     * @return bool True when {@see emit()} was called with it, false when it was
     *              not — and false, rather than an error, when there is no
     *              request in scope to have events at all.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function captured(MetaEvent $event): bool;

    /**
     * Forgets everything reported so far.
     *
     * Called at the start of a request rather than at its end, so that a stack
     * arriving with anything on it — from a runtime that reused its storage, or
     * from a handler that ran outside the normal path — cannot make the next
     * response claim something the request never did.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function flush(): void;
}
