<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Events\IMetaEventStack;
use App\Events\MetaEvent;

/**
 * The meta event stack, in a plain array instead of the coroutine context.
 *
 * A hand-written fake rather than a mock, for the same reason
 * {@see InMemoryPermissionRepository} is one: the collaborator needs real
 * behaviour. A test asserting that a cache hit was reported has to be able to
 * read the event back, and a mock would only prove that the mock agrees with the
 * test.
 *
 * It is not mocked-because-infrastructure either — the stack is an application
 * concern. It is faked because the production implementation stores its events
 * in `OpenSwoole\Coroutine::getContext()`, and a Pest test does not run inside a
 * coroutine: {@see \App\Events\Interno\CoroutineMetaEventStack::emit()} returns
 * immediately when the coroutine id is not positive, so the real one is silently
 * inert here and every assertion about an emitted event would pass against a use
 * case that emitted nothing.
 *
 * That coroutine binding is deliberate in production and documented in ADR 0010:
 * the object graph is built once per worker, so a field on the stack would be
 * shared by every request in flight and one request's hit would mark another's
 * response.
 */
final class InMemoryMetaEventStack implements IMetaEventStack
{
    /**
     * @var array<string, true> Emitted events, keyed by their backing value.
     */
    private array $events = [];

    public function emit(MetaEvent $event): void
    {
        $this->events[$event->value] = true;
    }

    public function captured(MetaEvent $event): bool
    {
        return isset($this->events[$event->value]);
    }

    public function flush(): void
    {
        $this->events = [];
    }
}
