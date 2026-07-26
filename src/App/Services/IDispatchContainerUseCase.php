<?php

declare(strict_types=1);

namespace App\Services;

use App\Commands\Container\DispatchContainerCommand;
use Shared\Exceptions\Result;

interface IDispatchContainerUseCase
{
    /**
     * @return Result<null> Void on success; 404 (missing) or 409 (not sealed).
     */
    public function execute(DispatchContainerCommand $command): Result;
}
