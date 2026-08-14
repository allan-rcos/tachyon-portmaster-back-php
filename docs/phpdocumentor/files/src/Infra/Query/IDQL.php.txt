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

    /**
     * What distinguishes this query from another of the same kind.
     *
     * This is the query's identity for caching purposes, and the DQL writes it
     * because the DQL is what knows what the query is. It used to be assembled
     * by the caller in the Rust implementation this mirrors, with a prefix per
     * service and the discipline to remember every filter — and a filter nobody
     * added to the key made two different queries read the same entry.
     *
     * **It must include every constructor argument**, in the normalised form
     * {@see toSql()} actually queries with rather than the raw one. A page is
     * identified by the id it resumes from, not by the cursor token that encodes
     * it: a token minted for other filters is discarded by {@see \Infra\Query\Cursor},
     * so two tokens that both mean "start from the beginning" are the same
     * query and belong on the same key.
     *
     * It does not come out of the SQL. The compiled statement separates text
     * from bound values, and the text alone is identical between two pages of
     * the same `SELECT`.
     *
     * Every DQL answers this, including the ones nothing caches today — the key
     * is what the query *is*, not the decision to store it, and
     * {@see \Infra\Repository\IViewCacheRepository} explains which reads go
     * through the cache and why the reads by id do not.
     *
     * @return string Stable across calls and unique to the parameters; kept
     *                readable rather than digested, because an entry that
     *                cannot say which query it belongs to is hard to debug. The
     *                cache declines to store a key longer than its column, so
     *                length is bounded there and not here.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function cacheKey(): string;
}
