<?php

/**
 * Get Container Use Case.
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

use App\Queries\Container\GetContainerQuery;
use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Services\IGetContainerUseCase;
use Infra\Query\Container\ContainerViewItem;
use Infra\Query\Interno\GetContainerDQL;
use Infra\Query\IQueryRepository;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

/**
 * Reads one container, if the caller may.
 *
 * Follows the single-read shape documented on {@see GetProductUseCase}:
 * authorize, run the DQL, turn a null view into a 404.
 *
 * @see IGetContainerUseCase The contract this implements.
 * @see GetProductUseCase The shape.
 * @uses IQueryRepository Runs the query; this layer never builds SQL.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class GetContainerUseCase implements IGetContainerUseCase
{
    use AuthorizesWithPermission;

    /**
     * Declares `container:read`, shared with {@see ListContainersUseCase}.
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
        $this->permission = $this->declarePermission($registrar, 'container:read');
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function execute(GetContainerQuery $query): Result
    {
        $denied = $this->authorize($query->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        $result = $this->queries->run(new GetContainerDQL($query->id));
        if (!$result->isSuccess()) {
            return Result::failure($result->getErrorId());
        }

        $item = $result->getValue();
        if (!$item instanceof ContainerViewItem) {
            return Result::failure(Leaf::newError(new LeafContext(
                message: "Container with id $query->id not found",
                code: 404,
            )));
        }

        return Result::success($item);
    }
}
