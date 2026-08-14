<?php

/**
 * Get Metrics Use Case.
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

use App\Queries\Metrics\GetMetricsQuery;
use App\Events\IMetaEventStack;
use App\Events\MetaEvent;
use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Services\IGetMetricsUseCase;
use Infra\Query\Interno\MetricsDQL;
use Infra\Query\IQueryRepository;
use Infra\Query\Metrics\MetricsView;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Shared\Exceptions\Result;

/**
 * Reads the yard-wide metrics snapshot, if the caller may.
 *
 * Follows the list-read shape documented on {@see ListProductsUseCase}:
 * authorize, run the DQL, return what comes back. The DQL takes no parameters,
 * so this is the thinnest use case in the layer — authorization is very nearly
 * all of it.
 *
 * @see IGetMetricsUseCase The contract this implements.
 * @see ListProductsUseCase The shape.
 * @uses IQueryRepository Runs the query; this layer never builds SQL.
 * @uses IViewCacheRepository Answers the read before the database is asked;
 *       see {@see ListProductsUseCase} for the shape and why it is written
 *       out here rather than hidden behind the runner.
 * @uses IMetaEventStack Reports the hit, which the response header is built from.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class GetMetricsUseCase implements IGetMetricsUseCase
{
    use AuthorizesWithPermission;

    /**
     * Declares `metrics:read`, its own — the snapshot spans every resource, so
     * it borrows no other feature's permission.
     *
     * @param  IQueryRepository  $queries  The read-side runner.
     * @param  IViewCacheRepository  $views  Consulted before the runner, and
     *                                       written to after it.
     * @param  IMetaEventStack  $events  Told when a hit is what answered,
     *                                   so the response can say so.
     * @param  IRegisterPermissionUseCase  $registrar  Consulted once, here.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IQueryRepository $queries,
        private IViewCacheRepository $views,
        private IMetaEventStack $events,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'metrics:read');
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function execute(GetMetricsQuery $query): Result
    {
        $denied = $this->authorize($query->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        $dql = new MetricsDQL();
        $key = $dql->cacheKey();

        $hit = $this->views->get(ViewCacheGroup::Metrics, $key)->getValue();
        if ($hit instanceof MetricsView) {
            // On the hit, not on the lookup: every read consults the cache.
            $this->events->emit(MetaEvent::ViewCacheHit);

            return Result::success($hit);
        }

        $result = $this->queries->run($dql);
        if (!$result->isSuccess()) {
            return Result::failure($result->getErrorId());
        }

        $this->views->put(ViewCacheGroup::Metrics, $key, $result->getValue());

        return $result;
    }
}
