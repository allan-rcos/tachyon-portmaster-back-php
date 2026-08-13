<?php

/**
 * Query Contract.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra\Query;

/**
 * A Data Query Language object: a self-contained read query.
 *
 * It owns both the backend compilation (via {@see ISqlDQL} and any future
 * backend facet) and the hydration of raw rows into its typed view. **Only the
 * DQL knows the concrete View/ViewItem types** — the {@see IQueryRepository}
 * runner stays agnostic and generic, so execution is backend-swappable while the
 * return stays typed through `TView`.
 *
 * @see ISqlDQL The SQL facet, inherited.
 * @see IQueryRepository The runner that executes one of these.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @template TView
 */
interface IDQL extends ISqlDQL
{
    /**
     * Builds the view from the rows returned by the query.
     *
     * This is the only place the concrete view type is known, which is what lets
     * the runner stay generic. An empty result set is hydrated like any other —
     * into an empty view, not into a failure.
     *
     * @param  list<array<string, mixed>>  $rows  As the driver returned them, in
     *                                            the query's own order.
     * @return TView The view this query is typed to produce.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function hydrate(array $rows): mixed;
}
