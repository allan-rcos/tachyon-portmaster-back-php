<?php

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

final readonly class GetContainerUseCase implements IGetContainerUseCase
{
    use AuthorizesWithPermission;

    public function __construct(
        private IQueryRepository $queries,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'container:read');
    }

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
