<?php

declare(strict_types=1);

namespace App\Services\Interno;

use App\Queries\Metrics\GetMetricsQuery;
use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Services\IGetMetricsUseCase;
use Infra\Query\Interno\MetricsDQL;
use Infra\Query\IQueryRepository;
use Shared\Exceptions\Result;

final readonly class GetMetricsUseCase implements IGetMetricsUseCase
{
    use AuthorizesWithPermission;

    public function __construct(
        private IQueryRepository $queries,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'metrics:read');
    }

    public function execute(GetMetricsQuery $query): Result
    {
        $denied = $this->authorize($query->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        return $this->queries->run(new MetricsDQL());
    }
}
