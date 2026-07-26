<?php

declare(strict_types=1);

namespace App\Services\Interno;

use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Queries\Role\ListRolesQuery;
use App\Services\IListRolesUseCase;
use Infra\Query\Interno\ListRolesDQL;
use Infra\Query\IQueryRepository;
use Shared\Exceptions\Result;

final readonly class ListRolesUseCase implements IListRolesUseCase
{
    use AuthorizesWithPermission;

    public function __construct(
        private IQueryRepository $queries,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'role:list');
    }

    public function execute(ListRolesQuery $query): Result
    {
        $denied = $this->authorize($query->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        return $this->queries->run(new ListRolesDQL(
            cursor: $query->cursor,
            limit: $query->limit,
            search: $query->search,
        ));
    }
}
