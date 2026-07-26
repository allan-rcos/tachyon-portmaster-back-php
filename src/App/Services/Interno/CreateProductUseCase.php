<?php

declare(strict_types=1);

namespace App\Services\Interno;

use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Commands\Product\CreateProductCommand;
use App\Services\ICreateProductUseCase;
use Domain\Models\IProduct;
use Domain\TableModules\IProductTM;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IProductRepository;
use Shared\Exceptions\Result;

final readonly class CreateProductUseCase implements ICreateProductUseCase
{
    use AuthorizesWithPermission;

    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IProductRepository $products,
        private IProductTM $productTM,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'product:create');
    }

    public function execute(CreateProductCommand $command): Result
    {
        $denied = $this->authorize($command->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        $begin = $this->unitOfWork->begin();
        if (!$begin->isSuccess()) {
            return Result::failure($begin->getErrorId());
        }

        $built = $this->productTM->create($command->name, $command->density,
            $command->riskClass);
        if (!$built->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($built->getErrorId());
        }

        /** @var IProduct $product */
        $product = $built->getValue();

        $inserted = $this->products->insert($product);
        if (!$inserted->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($inserted->getErrorId());
        }

        $commit = $this->unitOfWork->commit();
        if (!$commit->isSuccess()) {
            return Result::failure($commit->getErrorId());
        }

        return Result::success($product);
    }
}
