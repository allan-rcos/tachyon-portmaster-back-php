<?php

/**
 * List Products Use Case.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App\Services\Interno;

use App\Events\IMetaEventStack;
use App\Events\MetaEvent;
use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Queries\Product\ListProductsQuery;
use App\Services\IListProductsUseCase;
use Infra\Query\Interno\ListProductsDQL;
use Infra\Query\IQueryRepository;
use Infra\Query\Product\ProductListView;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Shared\Exceptions\Result;

/**
 * Lists products, if the caller may.
 *
 * **The shape every cached list use case in this layer follows**, written out
 * once here so the others need only say how they differ:
 *
 *  1. **authorize**;
 *  2. **build the DQL** from the query's parameters, passed through untouched;
 *  3. **ask the cache**, under the DQL's own key, and answer a hit;
 *  4. **run the query** on a miss;
 *  5. **store the result**, ignoring whether the store accepted it.
 *
 * **Step 3 is after step 1 and never before.** The cache does not know who is
 * asking — it is keyed by the query, not by the caller — so consulting it first
 * would hand data to someone who may not read it.
 *
 * Steps 3 and 5 are written out here, in each of the six, rather than hidden
 * behind a decorator on {@see IQueryRepository}: the group a write drops is an
 * application decision, and the ADR has the rest of that argument.
 *
 * No unit of work — a read opens no boundary. No 404 either: an empty page is a
 * successful empty view, and a caller asking for products when there are none
 * has not made a mistake. That is what separates a list read from a single read
 * ({@see GetProductUseCase}), which does turn "nothing" into a 404 — and which
 * is not cached at all.
 *
 * The cursor is opaque here as everywhere above the infra layer; this class
 * neither decodes nor validates it.
 *
 * @see IListProductsUseCase The contract this implements.
 * @see GetProductUseCase The single-read shape, which differs on the 404.
 * @uses IQueryRepository Runs the query; this layer never builds SQL.
 * @uses IViewCacheRepository Answers the read before the database is asked.
 * @uses IMetaEventStack Reports the hit, which the response header is built from.
 * @see docs/adr/0010-read-cache-in-a-memory-table.md Why the cache is called from here rather than from a decorator.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class ListProductsUseCase implements IListProductsUseCase
{
    use AuthorizesWithPermission;

    /**
     * Declares `product:read`, the same slug {@see GetProductUseCase} declares —
     * registration is idempotent, and reading one product or many is one
     * privilege.
     *
     * @param  IQueryRepository  $queries  The read-side runner.
     * @param  IViewCacheRepository  $views  Consulted before the runner, and
     *                                       written to after it.
     * @param  IMetaEventStack  $events  Told when a hit is what answered,
     *                                   so the response can say so.
     * @param  IRegisterPermissionUseCase  $registrar  Consulted once, here, and
     *                                                 not retained.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IQueryRepository $queries,
        private IViewCacheRepository $views,
        private IMetaEventStack $events,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'product:read');
    }

    /**
     * Authorizes, answers from the cache when it can, and otherwise runs the
     * query and stores what came back.
     *
     * The hit is checked with an `instanceof` rather than taken on trust: an
     * entry written by an earlier deploy could deserialize into something else
     * entirely, and treating that as a miss keeps a stale format from reaching
     * the controller. Storing is best-effort by contract, so its result is not
     * inspected — the caller already holds the correct page.
     *
     * @param  ListProductsQuery  $query  Carries the caller and the paging and
     *                                    filter parameters.
     * @return Result<ProductListView> The page, empty when nothing matched; a
     *                                 403 when the caller lacks `product:read`,
     *                                 a 500 when the read failed.
     *
     * @copyright 2026 Tachyon
     */
    public function execute(ListProductsQuery $query): Result
    {
        $denied = $this->authorize($query->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        $dql = new ListProductsDQL(
            cursor: $query->cursor,
            limit: $query->limit,
            search: $query->search,
        );
        $key = $dql->cacheKey();

        $hit = $this->views->get(ViewCacheGroup::Product, $key)->getValue();
        if ($hit instanceof ProductListView) {
            // On the hit, not on the lookup: every read consults the cache.
            $this->events->emit(MetaEvent::ViewCacheHit);

            return Result::success($hit);
        }

        $result = $this->queries->run($dql);
        if (!$result->isSuccess()) {
            return Result::failure($result->getErrorId());
        }

        $this->views->put(ViewCacheGroup::Product, $key, $result->getValue());

        return $result;
    }
}
