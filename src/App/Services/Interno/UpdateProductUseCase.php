<?php

declare(strict_types=1);

namespace App\Services\Interno;

use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Commands\Product\UpdateProductCommand;
use App\Services\IUpdateProductUseCase;
use Domain\Models\IProduct;
use Domain\TableModules\IProductTM;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IProductRepository;
use Shared\Exceptions\Result;

final readonly class UpdateProductUseCase implements IUpdateProductUseCase
{
    use AuthorizesWithPermission;

    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IProductRepository $products,
        private IProductTM $productTM,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'product:update');
    }

    public function execute(UpdateProductCommand $command): Result
    {
        $denied = $this->authorize($command->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        $begin = $this->unitOfWork->begin();
        if (!$begin->isSuccess()) {
            return Result::failure($begin->getErrorId());
        }

        $existing = $this->products->findById($command->id);
        if (!$existing->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($existing->getErrorId());
        }

        $built = $this->productTM->update($command->id, $command->name, $command->density, $command->riskClass);
        if (!$built->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($built->getErrorId());
        }

        /** @var IProduct $product */
        $product = $built->getValue();

        $updated = $this->products->update($product);
        if (!$updated->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($updated->getErrorId());
        }

        $commit = $this->unitOfWork->commit();
        if (!$commit->isSuccess()) {
            return Result::failure($commit->getErrorId());
        }

        return Result::success($product);
    }
}
