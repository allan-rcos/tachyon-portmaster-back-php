<?php

declare(strict_types=1);

namespace Infra\Query;

use Shared\Exceptions\Result;

/**
 * Runs a {@see IDQL} against the read store and returns its view.
 *
 * The read side of CQRS: agnostic execution (it neither builds SQL nor knows the
 * view type) with a typed return via the DQL's `TView`. Swapping the store means
 * a new implementation (e.g. `MongoQueryRepository`) bound behind this port — no
 * caller changes.
 */
interface IQueryRepository
{
    /**
     * @template TView
     * @param  IDQL<TView>  $dql
     * @return Result<TView>
     */
    public function run(IDQL $dql): Result;
}
