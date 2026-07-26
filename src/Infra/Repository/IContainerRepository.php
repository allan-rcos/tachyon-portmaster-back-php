<?php

namespace Infra\Repository;

use Domain\Models\IContainer;
use Shared\Exceptions\Result;

interface IContainerRepository
{
    /**
     * @return Result<IContainer> The container, or failure (404) when not found.
     */
    public function findById(string $id): Result;

    /**
     * @return Result<null>
     */
    public function insert(IContainer $container): Result;

    /**
     * @return Result<null>
     */
    public function update(IContainer $container): Result;

    /**
     * @return Result<null>
     */
    public function delete(string $id): Result;
}
