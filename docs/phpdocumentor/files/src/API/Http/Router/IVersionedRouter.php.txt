<?php

/**
 * Versioned Router Contract.
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

use Ds\Set;

/**
 * One published version of the REST contract: its number, and every route it
 * serves.
 *
 * A version is a class rather than a constant so that the next one is a *new*
 * file beside this one instead of an edit to the last — which is what calling a
 * published version frozen has to mean in practice. {@see RouterHub} collects
 * the implementations, mounts each under its own `/v<n>` group, and refuses to
 * boot if two of them claim the same number.
 *
 * A router lists what it serves **in full**, not a delta against the previous
 * version. A route missing from `V2Router` is simply not served under `/v2`,
 * and that absence is how a route that only ever existed in v1 is written.
 *
 * @see \API\Http\Router\RouterHub Collects and mounts these.
 * @see \API\Http\Router\Route What a table is made of.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IVersionedRouter
{
    /**
     * This version's number, as a literal.
     *
     * It is the single source for two things: the group prefix the routes are
     * mounted under (`/v1`), and the order versions are ranked in when the
     * unversioned alias picks a winner. Neither is written down anywhere else.
     *
     * @return int Positive, and unique across all routers.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function getVersion(): int;

    /**
     * Every route this version publishes.
     *
     * @return Set<Route> Insertion order is preserved, which is what keeps a
     *                    literal segment ahead of the `{id}` pattern that would
     *                    also match it.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function routes(): Set;
}
