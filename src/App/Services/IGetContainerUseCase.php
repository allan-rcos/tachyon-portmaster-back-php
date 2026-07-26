<?php

declare(strict_types=1);

namespace App\Services;

use App\Queries\Container\GetContainerQuery;
use Infra\Query\Container\ContainerViewItem;
use Shared\Exceptions\Result;

interface IGetContainerUseCase
{
    /**
     * @return Result<ContainerViewItem> The container, or 404 when not found.
     */
    public function execute(GetContainerQuery $query): Result;
}
