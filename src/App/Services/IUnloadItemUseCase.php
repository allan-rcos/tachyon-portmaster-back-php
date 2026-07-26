<?php

declare(strict_types=1);

namespace App\Services;

use App\Commands\Manifest\UnloadItemCommand;
use Domain\Models\IContainer;
use Shared\Exceptions\Result;

interface IUnloadItemUseCase
{
    /**
     * @return Result<IContainer> The container's new state after the unload.
     */
    public function execute(UnloadItemCommand $command): Result;
}
