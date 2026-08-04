<?php

/**
 * Router Hub.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Http\Router;

use API\Http\Router\Interno\V1Router;
use Ds\Set;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use RuntimeException;

use function FastRoute\simpleDispatcher;

/**
 * Joins every published {@see IVersionedRouter} into one dispatcher.
 *
 * Each version is mounted under its own `/v<n>` group, taken from the number the
 * router itself declares — the prefix has no second source. On top of that, the
 * unversioned path space is served too: `/products` reaches the **newest version
 * that publishes it**, decided per route rather than globally. A route carried
 * from v1 into v2 answers from v2 at the root; one that only ever existed in v1
 * keeps answering from v1.
 *
 * The merge is not written by hand. {@see Route} is a {@see \Ds\Key} whose
 * identity is method and path, so filling a {@see Set} newest version first
 * leaves exactly one row per route — the newest, because a set keeps what it saw
 * first. Doing it any other way would not merely be longer: FastRoute throws on
 * two routes matching the same method and path, so the duplicates have to be
 * gone *before* registration, not caught during it.
 *
 * The root is a convenience, not a contract. What it points at moves the day a
 * new version publishes the same route, so a client that means v1 should ask for
 * `/v1` — which is what `swagger.json` tells it to do in its `servers` block,
 * and what the front end already does.
 *
 * @see \API\Http\Router\IVersionedRouter One version's table.
 * @see \API\Http\Middleware\RouteDispatchMiddleware What matches against the result.
 * @see \API\Interno\ApiProvider What builds it, once, at WorkerStart.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final class RouterHub
{
    /**
     * Where the whole API hangs, versions included.
     *
     * Empty: the API *is* the root of this process. It exists so that mounting
     * everything under some long path costs one string and no other change —
     * but the `/api` a browser sees is **not** that path. That prefix belongs to
     * the reverse proxy and is stripped before the request arrives, and the
     * server-side renderer, which talks to the loopback port directly, never had
     * it at all. Putting `/api` here would break both callers at once.
     *
     * @var string
     */
    private const string PREFIX = '';

    /**
     * Every version this build publishes.
     *
     * A new version is a new entry here and a new class beside {@see V1Router};
     * the order is irrelevant, since ranking comes from
     * {@see IVersionedRouter::getVersion()}.
     *
     * @return list<IVersionedRouter> In any order.
     *
     * @copyright 2026 Tachyon
     */
    private static function routers(): array
    {
        return [
            new V1Router(),
        ];
    }

    /**
     * Builds the dispatcher over every published version.
     *
     * Rebuilt per call rather than cached: it is constructed once at
     * `WorkerStart` and held for the worker's lifetime, so there is nothing for
     * a cache to save.
     *
     * @return Dispatcher Ready to match a method and path.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function dispatcher(): Dispatcher
    {
        return self::dispatcherFor(self::routers());
    }

    /**
     * Builds the dispatcher over an explicit set of versions.
     *
     * @param list<IVersionedRouter> $routers In any order; ranked here.
     *
     * @return Dispatcher Ready to match a method and path.
     *
     * @throws RuntimeException If two routers declare the same version number.
     *                          This is a wiring mistake, so it surfaces at
     *                          `WorkerStart` — before any traffic — rather than
     *                          as a per-request failure.
     *
     * @copyright 2026 Tachyon
     *
     * @internal Seam for the routing test; the published surface is
     *           {@see dispatcher()}.
     */
    public static function dispatcherFor(array $routers): Dispatcher
    {
        $ranked = self::rank($routers);

        return simpleDispatcher(static function (RouteCollector $r) use ($ranked): void {
            $r->addGroup(self::PREFIX, static function (RouteCollector $r) use ($ranked): void {
                foreach ($ranked as $number => $router) {
                    $routes = $router->routes()->toArray();

                    $r->addGroup('/v'.$number, static function (RouteCollector $r) use ($routes): void {
                        foreach ($routes as $route) {
                            $r->addRoute($route->method, $route->path, $route->handler);
                        }
                    });
                }

                foreach (self::newestPerRoute($ranked)->toArray() as $route) {
                    $r->addRoute($route->method, $route->path, $route->handler);
                }
            });
        });
    }

    /**
     * The unversioned table: every route, on its newest publisher.
     *
     * The merge is the set, not the loop. {@see Route} hashes on method and path
     * alone and a {@see Set} keeps what it saw first, so walking the versions
     * newest first leaves exactly one row per route — and leaves it pointing at
     * the newest version that still publishes it, which for a route dropped
     * along the way is not the newest version overall.
     *
     * @param array<int, IVersionedRouter> $ranked Newest first.
     *
     * @return Set<Route> Ready to register with no prefix.
     *
     * @copyright 2026 Tachyon
     */
    private static function newestPerRoute(array $ranked): Set
    {
        $rows = [];

        foreach ($ranked as $router) {
            foreach ($router->routes()->toArray() as $route) {
                $rows[] = $route;
            }
        }

        // The set does the merging: it takes them in this order and keeps the
        // first of each.
        return new Set($rows);
    }

    /**
     * Indexes the routers by version number, refusing a repeated one.
     *
     * @param list<IVersionedRouter> $routers In any order — the caller's order
     *                                        carries no meaning, the numbers do.
     *
     * @return array<int, IVersionedRouter> Keyed by version number, **newest
     *                                      first**, which is the order the
     *                                      unversioned merge depends on.
     *
     * @throws RuntimeException If two routers declare the same number, naming
     *                          both classes and the number they collide on.
     *
     * @copyright 2026 Tachyon
     */
    private static function rank(array $routers): array
    {
        $ranked = [];

        foreach ($routers as $router) {
            $number = $router->getVersion();

            if (isset($ranked[$number])) {
                throw new RuntimeException(sprintf(
                    'Two routers declare version %d: %s and %s. A version number addresses exactly one table.',
                    $number,
                    $ranked[$number]::class,
                    $router::class,
                ));
            }

            $ranked[$number] = $router;
        }

        krsort($ranked);

        return $ranked;
    }
}
