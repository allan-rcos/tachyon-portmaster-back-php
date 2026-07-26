<?php

declare(strict_types=1);

namespace App\Services\Interno;

use App\Queries\Role\GetRoleQuery;
use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Services\IGetRoleUseCase;
use Infra\Query\Interno\GetRoleDQL;
use Infra\Query\IQueryRepository;
use Infra\Query\Role\RoleViewItem;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

final readonly class GetRoleUseCase implements IGetRoleUseCase
{
    use AuthorizesWithPermission;

    public function __construct(
        private IQueryRepository $queries,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'role:list');
    }

    public function execute(GetRoleQuery $query): Result
    {
        $denied = $this->authorize($query->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        $result = $this->queries->run(new GetRoleDQL($query->id));
        if (!$result->isSuccess()) {
            return Result::failure($result->getErrorId());
        }

        $item = $result->getValue();
        if (!$item instanceof RoleViewItem) {
            return Result::failure(Leaf::newError(new LeafContext(
                message: "Role with id $query->id not found",
                code: 404,
            )));
        }

        return Result::success($item);
    }
}
