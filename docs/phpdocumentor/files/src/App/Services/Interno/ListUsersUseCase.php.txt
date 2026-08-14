<?php

/**
 * List Users Use Case.
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
use App\Queries\User\ListUsersQuery;
use App\Services\IListUsersUseCase;
use Infra\Query\Interno\ListUsersDQL;
use Infra\Query\IQueryRepository;
use Infra\Query\User\UserListView;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Shared\Exceptions\Result;

/**
 * Lists users with their roles, if the caller may.
 *
 * Follows the list-read shape documented on {@see ListProductsUseCase}, over the
 * one endpoint that pages by offset.
 *
 * @see IListUsersUseCase The contract this implements.
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
final readonly class ListUsersUseCase implements IListUsersUseCase
{
    use AuthorizesWithPermission;

    /**
     * Declares `user:list`, distinct from the `user:get` that
     * {@see GetUserUseCase} declares.
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
        $this->permission = $this->declarePermission($registrar, 'user:list');
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function execute(ListUsersQuery $query): Result
    {
        $denied = $this->authorize($query->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        $dql = new ListUsersDQL(
            page: $query->page,
            limit: $query->limit,
        );
        $key = $dql->cacheKey();

        $hit = $this->views->get(ViewCacheGroup::User, $key)->getValue();
        if ($hit instanceof UserListView) {
            // On the hit, not on the lookup: every read consults the cache.
            $this->events->emit(MetaEvent::ViewCacheHit);

            return Result::success($hit);
        }

        $result = $this->queries->run($dql);
        if (!$result->isSuccess()) {
            return Result::failure($result->getErrorId());
        }

        $this->views->put(ViewCacheGroup::User, $key, $result->getValue());

        return $result;
    }
}
