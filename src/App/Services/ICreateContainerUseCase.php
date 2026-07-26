<?php

declare(strict_types=1);

namespace App\Services;

use App\Commands\Container\CreateContainerCommand;
use Domain\Models\IContainer;
use Shared\Exceptions\Result;

interface ICreateContainerUseCase
{
    /**
     * @return Result<IContainer>
     */
    public function execute(CreateContainerCommand $command): Result;
}
