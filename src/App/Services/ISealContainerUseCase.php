<?php

declare(strict_types=1);

namespace App\Services;

use App\Commands\Container\SealContainerCommand;
use Shared\Exceptions\Result;

interface ISealContainerUseCase
{
    /**
     * @return Result<null> Void on success; 404 (missing) or 409 (preconditions).
     */
    public function execute(SealContainerCommand $command): Result;
}
