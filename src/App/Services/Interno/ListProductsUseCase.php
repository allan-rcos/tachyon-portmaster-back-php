<?php

declare(strict_types=1);

namespace App\Services\Interno;

use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Queries\Product\ListProductsQuery;
use App\Services\IListProductsUseCase;
use Infra\Query\Interno\ListProductsDQL;
use Infra\Query\IQueryRepository;
use Shared\Exceptions\Result;

final readonly class ListProductsUseCase implements IListProductsUseCase
{
    use AuthorizesWithPermission;

    public function __construct(
        private IQueryRepository $queries,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'product:read');
    }

    public function execute(ListProductsQuery $query): Result
    {
        $denied = $this->authorize($query->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        return $this->queries->run(new ListProductsDQL(
            cursor: $query->cursor,
            limit: $query->limit,
            search: $query->search,
        ));
    }
}
