<?php

declare(strict_types=1);

namespace App\Services\Interno;

use App\Queries\Product\GetProductQuery;
use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Services\IGetProductUseCase;
use Infra\Query\Interno\GetProductDQL;
use Infra\Query\IQueryRepository;
use Infra\Query\Product\ProductViewItem;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

final readonly class GetProductUseCase implements IGetProductUseCase
{
    use AuthorizesWithPermission;

    public function __construct(
        private IQueryRepository $queries,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'product:read');
    }

    public function execute(GetProductQuery $query): Result
    {
        $denied = $this->authorize($query->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        $result = $this->queries->run(new GetProductDQL($query->id));
        if (!$result->isSuccess()) {
            return Result::failure($result->getErrorId());
        }

        $item = $result->getValue();
        if (!$item instanceof ProductViewItem) {
            return Result::failure(Leaf::newError(new LeafContext(
                message: "Product with id $query->id not found",
                code: 404,
            )));
        }

        return Result::success($item);
    }
}
