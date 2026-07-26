<?php

declare(strict_types=1);

namespace App\Services;

use App\Commands\Container\DeleteContainerCommand;
use Shared\Exceptions\Result;

interface IDeleteContainerUseCase
{
    /**
     * @return Result<null> Void on success, 404 when the container does not exist.
     */
    public function execute(DeleteContainerCommand $command): Result;
}
