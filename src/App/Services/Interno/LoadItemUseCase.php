<?php

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

final readonly class LoadItemUseCase implements ILoadItemUseCase
{
    use AuthorizesWithPermission;

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
