<?php

declare(strict_types=1);

namespace App\Services;

use App\Commands\Manifest\LoadItemCommand;
use Domain\Models\IContainer;
use Shared\Exceptions\Result;

interface ILoadItemUseCase
{
    /**
     * @return Result<IContainer> The container's new state after the load.
     */
    public function execute(LoadItemCommand $command): Result;
}
