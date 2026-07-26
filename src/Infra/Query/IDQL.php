<?php

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
 * @template TView
 */
interface IDQL extends ISqlDQL
{
    /**
     * Builds the view from the rows returned by the query.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return TView
     */
    public function hydrate(array $rows): mixed;
}
