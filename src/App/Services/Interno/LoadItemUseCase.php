<?php

/**
 * Load Item Use Case.
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
use App\Commands\Manifest\LoadItemCommand;
use App\Services\ILoadItemUseCase;
use Domain\Models\IContainer;
use Domain\Models\IManifestCargo;
use Domain\Models\IManifestChange;
use Domain\Models\IProduct;
use Domain\TableModules\IManifestTM;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IContainerRepository;
use Infra\Repository\IManifestRepository;
use Infra\Repository\IProductRepository;
use Shared\Exceptions\Result;

/**
 * Loads a quantity of a product into a container, if the caller may and the
 * domain allows it.
 *
 * **The shape both manifest use cases follow**, the widest variant of the write
 * shape on {@see CreateProductUseCase}:
 *
 *  1. authorize;
 *  2. begin;
 *  3. **load three things** — the container, the product, and any existing cargo
 *     line for that pair. All three are inputs to the rule, not merely 404
 *     checks: capacity depends on what is already aboard, and weight depends on
 *     the product's density;
 *  4. hand them to the table module, which returns a
 *     {@see \Domain\Models\IManifestChange} describing every write to make;
 *  5. hand *that* to {@see ManifestPersistence}, which performs the writes and
 *     commits.
 *
 * The split at step 5 is why this class stops where it does: what to write is a
 * domain decision, how to write it is identical for loading and unloading, and
 * neither belongs here.
 *
 * @see ILoadItemUseCase The contract this implements.
 * @see UnloadItemUseCase The inverse, identically shaped.
 * @see ManifestPersistence Where the writes and the commit happen.
 * @uses IUnitOfWork The boundary this opens; closing it is delegated.
 * @uses IManifestTM Computes the change; the only place a rule is decided.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class LoadItemUseCase implements ILoadItemUseCase
{
    use AuthorizesWithPermission;

    /**
     * Declares `manifest:load`.
     *
     * Five collaborators, the most of any use case here — a load touches the
     * container, the product catalogue and the manifest, and the boundary is
     * what holds the three together.
     *
     * @param  IUnitOfWork  $unitOfWork  The boundary; never the connection.
     * @param  IContainerRepository  $containers  Loads the target container.
     * @param  IProductRepository  $products  Loads the product, whose density
     *                                        turns the quantity into a weight.
     * @param  IManifestRepository  $manifest  Loads any existing cargo line, and
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
        private IContainerRepository $containers,
        private IProductRepository $products,
        private IManifestRepository $manifest,
        private IManifestTM $manifestTM,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'manifest:load');
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function execute(LoadItemCommand $command): Result
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
        /** @var IManifestCargo|null $current */
        $current = $cargoResult->getValue();

        $changeResult = $this->manifestTM->load($container, $product, $command->quantity, $current);
        if (!$changeResult->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($changeResult->getErrorId());
        }

        /** @var IManifestChange $change */
        $change = $changeResult->getValue();

        return ManifestPersistence::commit($this->unitOfWork, $this->containers, $this->manifest, $change);
    }
}
