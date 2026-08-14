<?php

/**
 * Unload Item Use Case.
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
use App\Commands\Manifest\UnloadItemCommand;
use App\Services\IUnloadItemUseCase;
use Domain\Models\IContainer;
use Domain\Models\IManifestChange;
use Domain\Models\IProduct;
use Domain\TableModules\IManifestTM;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Infra\Repository\IContainerRepository;
use Infra\Repository\IManifestRepository;
use Infra\Repository\IProductRepository;
use Shared\Exceptions\Result;

/**
 * Removes a quantity of a product from a container, if the caller may and the
 * domain allows it.
 *
 * Follows the manifest shape documented on {@see LoadItemUseCase} exactly: load
 * the container, the product and the existing cargo line, let the table module
 * compute the change, and hand it to {@see ManifestPersistence}.
 *
 * The two differ in one table-module call and one permission. Everything else —
 * including what happens when a line drops to zero, which becomes a delete
 * rather than an update — is decided by the change object, not here.
 *
 * @see IUnloadItemUseCase The contract this implements.
 * @see LoadItemUseCase The inverse, and the shape.
 * @see ManifestPersistence Where the writes and the commit happen.
 * @uses IUnitOfWork The boundary this opens; closing it is delegated.
 * @uses IManifestTM Computes the change; the only place a rule is decided.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class UnloadItemUseCase implements IUnloadItemUseCase
{
    use AuthorizesWithPermission;

    /**
     * Declares `manifest:unload`, separate from `manifest:load`.
     *
     * @param  IUnitOfWork  $unitOfWork  The boundary; never the connection.
     * @param  IViewCacheRepository  $views  Told to drop the group once the
     *                                       commit has landed.
     * @param  IContainerRepository  $containers  Loads the target container.
     * @param  IProductRepository  $products  Loads the product being removed.
     * @param  IManifestRepository  $manifest  Loads the existing cargo line, and
     *                                         is written through
     *                                         {@see ManifestPersistence}.
     * @param  IManifestTM  $manifestTM  Computes the change and decides whether
     *                                   it is allowed.
     * @param  IRegisterPermissionUseCase  $registrar  Consulted once, here.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IViewCacheRepository $views,
        private IContainerRepository $containers,
        private IProductRepository $products,
        private IManifestRepository $manifest,
        private IManifestTM $manifestTM,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'manifest:unload');
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function execute(UnloadItemCommand $command): Result
    {
        $denied = $this->authorize($command->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        $begin = $this->unitOfWork->begin();
        if (!$begin->isSuccess()) {
            return Result::failure($begin->getErrorId());
        }

        $containerResult = $this->containers->findById($command->containerId);
        if (!$containerResult->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($containerResult->getErrorId());
        }

        $productResult = $this->products->findById($command->productId);
        if (!$productResult->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($productResult->getErrorId());
        }

        $cargoResult = $this->manifest->findCargo($command->containerId, $command->productId);
        if (!$cargoResult->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($cargoResult->getErrorId());
        }

        /** @var IContainer $container */
        $container = $containerResult->getValue();
        /** @var IProduct $product */
        $product = $productResult->getValue();
        // No line yet is the ordinary state of a product that has never been
        // in this container, so the lookup answers void rather than failing.
        $current = $cargoResult->isEmpty() ? null : $cargoResult->getValue();

        $changeResult = $this->manifestTM->unload($container, $product, $command->quantity, $current);
        if (!$changeResult->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($changeResult->getErrorId());
        }

        /** @var IManifestChange $change */
        $change = $changeResult->getValue();

        $persisted = ManifestPersistence::commit($this->unitOfWork, $this->containers,
            $this->manifest, $change);
        if (!$persisted->isSuccess()) {
            return $persisted;
        }

        // ManifestPersistence commits, so this is after the commit like
        // everywhere else.
        $this->views->invalidate(ViewCacheGroup::Container);

        return $persisted;
    }
}
