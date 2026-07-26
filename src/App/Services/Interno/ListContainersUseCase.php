<?php

declare(strict_types=1);

namespace App\Services\Interno;

use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Queries\Container\ListContainersQuery;
use App\Services\IListContainersUseCase;
use Infra\Query\Interno\ListContainersDQL;
use Infra\Query\IQueryRepository;
use Shared\Exceptions\Result;

final readonly class ListContainersUseCase implements IListContainersUseCase
{
    use AuthorizesWithPermission;

    public function __construct(
        private IQueryRepository $queries,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'container:read');
    }

    public function execute(ListContainersQuery $query): Result
    {
        $denied = $this->authorize($query->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        return $this->queries->run(new ListContainersDQL(
            cursor: $query->cursor,
            limit: $query->limit,
            search: $query->search,
            status: $query->status,
            statusIn: $query->statusIn,
        ));
    }
}
