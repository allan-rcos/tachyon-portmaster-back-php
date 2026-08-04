<?php

declare(strict_types=1);

namespace Tests\Doubles;

use API\Http\Router\IVersionedRouter;
use API\Http\Router\Route;
use Ds\Set;

/**
 * An {@see IVersionedRouter} whose number and table are given at construction.
 *
 * The rule worth testing in {@see \API\Http\Router\RouterHub} is what happens
 * with *several* versions — which route the unversioned path resolves to, and
 * that two routers cannot claim the same number. Only v1 exists in the real
 * table, so the second version has to be a double: the alternative is waiting
 * for a v2 to write the test that would have caught its mistakes.
 *
 * Named rather than anonymous because the duplicate-number error names the
 * colliding classes, and a test that asserts on that message deserves a name it
 * can read.
 */
final readonly class FakeVersionedRouter implements IVersionedRouter
{
    /**
     * @param int         $version What {@see getVersion()} answers.
     * @param list<Route> $routes  What {@see routes()} publishes.
     */
    public function __construct(
        private int $version,
        private array $routes = [],
    ) {
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * @return Set<Route>
     */
    public function routes(): Set
    {
        return new Set($this->routes);
    }
}
