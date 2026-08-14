<?php

/**
 * Delete Product Use Case.
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

use App\Commands\Product\DeleteProductCommand;
use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Services\IDeleteProductUseCase;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Infra\Repository\IProductRepository;
use Shared\Exceptions\Result;

/**
 * Removes a product, if the caller may.
 *
 * **The shape every delete use case in this layer follows**: authorize, begin,
 * load, delete, commit — with no table module involved, because there is nothing
 * to validate about a removal.
 *
 * The load is what turns an id matching nothing into a 404: the repository's
 * `delete()` treats matching no row as a success, so without it deleting a
 * product twice would report success both times.
 *
 * @see IDeleteProductUseCase The contract this implements.
 * @see CreateProductUseCase The base write shape.
 * @uses IUnitOfWork The boundary this opens and closes.
 * @uses IProductRepository Loads the product, then removes it.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class DeleteProductUseCase implements IDeleteProductUseCase
{
    use AuthorizesWithPermission;

    /**
     * Declares `product:delete`.
     *
     * Takes no table module — unlike every other write here, there is no domain
     * rule to consult.
     *
     * @param  IUnitOfWork  $unitOfWork  The boundary; never the connection.
     * @param  IViewCacheRepository  $views  Told to drop the group once the
     *                                       commit has landed.
     * @param  IProductRepository  $products  Read from, then deleted from.
     * @param  IRegisterPermissionUseCase  $registrar  Consulted once, here.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IViewCacheRepository $views,
        private IProductRepository $products,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'product:delete');
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function execute(DeleteProductCommand $command): Result
    {
        $denied = $this->authorize($command->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        $begin = $this->unitOfWork->begin();
        if (!$begin->isSuccess()) {
            return Result::failure($begin->getErrorId());
        }

        // Surface 404 for a missing product (the repository's delete is a no-op).
        $existing = $this->products->findById($command->id);
        if (!$existing->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($existing->getErrorId());
        }

        $deleted = $this->products->delete($command->id);
        if (!$deleted->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($deleted->getErrorId());
        }

        $commit = $this->unitOfWork->commit();
        if (!$commit->isSuccess()) {
            return Result::failure($commit->getErrorId());
        }

        // After the commit, never before: a read in between would repopulate
        // the cache from the state this write replaces.
        $this->views->invalidate(ViewCacheGroup::Product);

        return Result::void();
    }
}
