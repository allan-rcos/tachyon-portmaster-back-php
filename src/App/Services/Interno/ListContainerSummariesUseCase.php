<?php

/**
 * List Container Summaries Use Case.
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

use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Queries\Container\ListContainerSummariesQuery;
use App\Services\IListContainerSummariesUseCase;
use Infra\Query\Interno\ListContainerSummariesDQL;
use Infra\Query\IQueryRepository;
use Shared\Exceptions\Result;

/**
 * Lists containers with their manifests, if the caller may.
 *
 * Follows the list-read shape documented on {@see ListProductsUseCase}. The
 * query's optional id is passed through, so this also serves "one container,
 * with its cargo" — {@see GetContainerUseCase} returns the container alone.
 *
 * @see IListContainerSummariesUseCase The contract this implements.
 * @see ListProductsUseCase The shape.
 * @uses IQueryRepository Runs the query; this layer never builds SQL.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class ListContainerSummariesUseCase implements IListContainerSummariesUseCase
{
    use AuthorizesWithPermission;

    /**
     * Declares `container:summary`, not the `container:read` its plainer
     * siblings share — seeing what every container carries is a broader thing to
     * be allowed.
     *
     * @param  IQueryRepository  $queries  The read-side runner.
     * @param  IRegisterPermissionUseCase  $registrar  Consulted once, here.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IQueryRepository $queries,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'container:summary');
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function execute(ListContainerSummariesQuery $query): Result
    {
        $denied = $this->authorize($query->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        return $this->queries->run(new ListContainerSummariesDQL(
            id: $query->id,
            cursor: $query->cursor,
            limit: $query->limit,
        ));
    }
}
