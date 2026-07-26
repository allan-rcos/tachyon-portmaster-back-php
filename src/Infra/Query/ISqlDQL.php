<?php

declare(strict_types=1);

namespace Infra\Query;

/**
 * The SQL-backend facet of a query: how the DQL compiles to a {@see SqlQuery}.
 *
 * A different backend adds its own facet (e.g. `IMongoDQL` with `toMongo()`) and
 * {@see IDQL} extends it too, forcing every query to implement the new method —
 * even if only to return a failure. The matching runner (SqlQueryRepository,
 * MongoQueryRepository) consumes the facet it understands.
 */
interface ISqlDQL
{
    public function toSql(): SqlQuery;
}
