<?php

/**
 * Route.
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

use Ds\Key;

/**
 * One line of a route table: a method, a path, and the controller entry point
 * the pair resolves to.
 *
 * **Identity is the method and the path, not the handler.** That is the exact
 * pair FastRoute refuses to see twice, so two rows agreeing on it are the same
 * route as far as routing is concerned, however different their handlers. Being
 * a {@see Key} with that identity is what lets a {@see \Ds\Set} answer the
 * unversioned-alias question on its own: fill one newest version first, and the
 * set keeps the newest publisher of every route and drops the rest.
 *
 * The same rule applies inside a single version. A router declaring the same
 * method and path twice keeps the first declaration and silently drops the
 * second — the set has no way to tell an accident from an override, and the
 * first-wins reading is the one that matches the cross-version rule.
 *
 * @see \API\Http\Router\IVersionedRouter Publishes a set of these.
 * @see \API\Http\Router\RouterHub Merges the sets and registers them.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class Route implements Key
{
    /**
     * @param string                    $method  The HTTP verb, uppercase.
     * @param string                    $path    Relative to the version prefix,
     *                                           in FastRoute syntax.
     * @param array{class-string, string} $handler The controller *interface* and
     *                                           the method on it.
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $handler,
    ) {
    }

    /**
     * The identity of this route, handler excluded.
     *
     * @return string Method and path, which is all routing distinguishes.
     *
     * @copyright 2026 Tachyon
     */
    public function hash(): string
    {
        return $this->method.' '.$this->path;
    }

    /**
     * Whether another value addresses the same method and path.
     *
     * @param mixed $other Anything; only a {@see Route} can match.
     *
     * @return bool True when both would collide in the dispatcher.
     *
     * @copyright 2026 Tachyon
     */
    public function equals(mixed $other): bool
    {
        return $other instanceof self
            && $other->method === $this->method
            && $other->path === $this->path;
    }
}
