<?php

declare(strict_types=1);

namespace App\Services\Interno;

use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Queries\Container\ListContainerSummariesQuery;
use App\Services\IListContainerSummariesUseCase;
use Infra\Query\Interno\ListContainerSummariesDQL;
use Infra\Query\IQueryRepository;
use Shared\Exceptions\Result;

final readonly class ListContainerSummariesUseCase implements IListContainerSummariesUseCase
{
    use AuthorizesWithPermission;

    public function __construct(
        private IQueryRepository $queries,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'container:summary');
    }

    public function execute(ListContainerSummariesQuery $query): Result
    {
        $denied = $this->authorize($query->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        return $this->queries->run(new ListContainerSummariesDQL(
            id: $query->id,
            cursor: $query->cursor,
            limit: $query->limit,
        ));
    }
}
