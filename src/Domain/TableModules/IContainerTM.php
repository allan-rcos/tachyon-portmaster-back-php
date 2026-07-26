<?php

namespace Domain\TableModules;

use Domain\Models\IContainer;
use Shared\Exceptions\Result;

interface IContainerTM
{
    /**
     * Builds a new container: current weight 0 and status Empty are forced,
     * ignoring any client attempt to set them.
     *
     * @return Result<IContainer> Failure (422) on invalid input.
     */
    public function create(string $code, float $maxCapacity): Result;

    /**
     * Produces the container with an updated capacity, re-validated.
     *
     * @return Result<IContainer> Failure (422) on invalid input.
     */
    public function update(IContainer $container, float $maxCapacity): Result;

    /**
     * Seals the container: requires status Loading and at least 10% of capacity
     * filled, then transitions to Sealed.
     *
     * @return Result<IContainer> Failure (409) when the preconditions aren't met.
     */
    public function seal(IContainer $container): Result;

    /**
     * Dispatches the container: requires status Sealed, then transitions to
     * InTransit.
     *
     * @return Result<IContainer> Failure (409) when not Sealed.
     */
    public function dispatch(IContainer $container): Result;
}
