<?php

declare(strict_types=1);

use API\Controllers\IContainerController;
use API\Controllers\IProductController;
use API\Controllers\IServerController;
use API\Http\Router\Route;
use API\Http\Router\RouterHub;
use FastRoute\Dispatcher;
use Tests\Doubles\FakeVersionedRouter;

/**
 * Resolves a method and path, returning the handler or null when unmatched.
 *
 * @return array{class-string, string}|null
 */
function resolve(Dispatcher $dispatcher, string $method, string $path): ?array
{
    $result = $dispatcher->dispatch($method, $path);

    return $result[0] === Dispatcher::FOUND ? $result[1] : null;
}

describe('the published route table', function () {
    beforeEach(function () {
        $this->dispatcher = RouterHub::dispatcher();
    });

    it('serves every route under its version prefix', function () {
        expect(resolve($this->dispatcher, 'GET', '/v1/products'))
            ->toBe([IProductController::class, 'list'])
            ->and(resolve($this->dispatcher, 'GET', '/v1/info'))
            ->toBe([IServerController::class, 'getInfo'])
            ->and(resolve($this->dispatcher, 'GET', '/v1/metrics'))
            ->not->toBeNull();
    });

    it('serves the same route unversioned, on the same handler', function () {
        expect(resolve($this->dispatcher, 'GET', '/products'))
            ->toBe(resolve($this->dispatcher, 'GET', '/v1/products'))
            ->and(resolve($this->dispatcher, 'GET', '/info'))
            ->toBe(resolve($this->dispatcher, 'GET', '/v1/info'));
    });

    // The `/api` a browser sends is the reverse proxy's, stripped before the
    // request gets here — and the server-side renderer never had it. Matching it
    // would break both callers at once.
    it('never answers to the proxy mount point', function () {
        expect(resolve($this->dispatcher, 'GET', '/api/v1/info'))->toBeNull()
            ->and(resolve($this->dispatcher, 'GET', '/api/info'))->toBeNull();
    });

    it('keeps a literal segment ahead of the id pattern that would also match it', function () {
        expect(resolve($this->dispatcher, 'GET', '/v1/containers/summary'))
            ->toBe([IContainerController::class, 'summary'])
            ->and(resolve($this->dispatcher, 'GET', '/containers/summary'))
            ->toBe([IContainerController::class, 'summary']);
    });

    it('captures a base62 id, not a numeric one', function () {
        $result = $this->dispatcher->dispatch('GET', '/v1/containers/aZ09');

        expect($result[0])->toBe(Dispatcher::FOUND)
            ->and($result[1])->toBe([IContainerController::class, 'get'])
            ->and($result[2])->toBe(['id' => 'aZ09']);
    });
});

describe('the unversioned alias across versions', function () {
    beforeEach(function () {
        $this->old = new FakeVersionedRouter(1, [
            new Route('GET', '/both', [IProductController::class, 'list']),
            new Route('GET', '/only-old', [IServerController::class, 'getInfo']),
        ]);
        $this->new = new FakeVersionedRouter(2, [
            new Route('GET', '/both', [IProductController::class, 'get']),
            new Route('GET', '/only-new', [IContainerController::class, 'list']),
        ]);

        // Deliberately out of order: ranking comes from getVersion(), not from
        // the order the routers are handed over.
        $this->dispatcher = RouterHub::dispatcherFor([$this->new, $this->old]);
    });

    it('resolves a shared route to the newest version that publishes it', function () {
        expect(resolve($this->dispatcher, 'GET', '/both'))
            ->toBe([IProductController::class, 'get']);
    });

    // The decision is per route, not per table: a route the newest version
    // dropped still answers, from the newest version that still has it.
    it('resolves a route only the older version publishes to that older version', function () {
        expect(resolve($this->dispatcher, 'GET', '/only-old'))
            ->toBe([IServerController::class, 'getInfo']);
    });

    it('keeps each version reachable under its own prefix', function () {
        expect(resolve($this->dispatcher, 'GET', '/v1/both'))
            ->toBe([IProductController::class, 'list'])
            ->and(resolve($this->dispatcher, 'GET', '/v2/both'))
            ->toBe([IProductController::class, 'get']);
    });

    it('does not lend one version the routes of another', function () {
        expect(resolve($this->dispatcher, 'GET', '/v2/only-old'))->toBeNull()
            ->and(resolve($this->dispatcher, 'GET', '/v1/only-new'))->toBeNull();
    });
});

describe('version numbering', function () {
    // A wiring mistake, so it has to surface at WorkerStart rather than as a
    // per-request failure — by then the worker is already serving one of the two
    // tables and nobody can tell which.
    it('refuses to build when two routers claim the same number', function () {
        RouterHub::dispatcherFor([
            new FakeVersionedRouter(1),
            new FakeVersionedRouter(1),
        ]);
    })->throws(RuntimeException::class, 'Two routers declare version 1');
});
