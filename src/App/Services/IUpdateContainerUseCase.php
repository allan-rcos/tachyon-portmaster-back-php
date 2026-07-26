<?php

declare(strict_types=1);

namespace App\Services;

use App\Commands\Container\UpdateContainerCommand;
use Domain\Models\IContainer;
use Shared\Exceptions\Result;

interface IUpdateContainerUseCase
{
    /**
     * @return Result<IContainer> The updated container, or 404 when not found.
     */
    public function execute(UpdateContainerCommand $command): Result;
}
