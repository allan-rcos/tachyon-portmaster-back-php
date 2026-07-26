<?php

declare(strict_types=1);

namespace App\Interno\Provider;

use App\Services\IGetMetricsUseCase;
use App\Services\Interno\GetMetricsUseCase;

final class MetricsProvider extends FeatureProvider
{
    public function getMetricsUseCase(): IGetMetricsUseCase
    {
        return new GetMetricsUseCase($this->infra->queryRepository(), $this->registrar());
    }
}
