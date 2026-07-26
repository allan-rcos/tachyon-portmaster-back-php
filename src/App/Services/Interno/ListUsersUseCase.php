<?php

declare(strict_types=1);

namespace App\Services\Interno;

use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Queries\User\ListUsersQuery;
use App\Services\IListUsersUseCase;
use Infra\Query\Interno\ListUsersDQL;
use Infra\Query\IQueryRepository;
use Shared\Exceptions\Result;

final readonly class ListUsersUseCase implements IListUsersUseCase
{
    use AuthorizesWithPermission;

    public function __construct(
        private IQueryRepository $queries,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'user:list');
    }

    public function execute(ListUsersQuery $query): Result
    {
        $denied = $this->authorize($query->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        return $this->queries->run(new ListUsersDQL(
            page: $query->page,
            limit: $query->limit,
        ));
    }
}
