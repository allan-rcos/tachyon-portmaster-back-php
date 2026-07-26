<?php

namespace Domain\TableModules;

use Domain\Enums\RiskClass;
use Domain\Models\IProduct;
use Shared\Exceptions\Result;

interface IProductTM
{
    /**
     * Builds a new product after validating input (name non-empty, density > 0).
     *
     * @return Result<IProduct> Failure (422) on invalid input.
     */
    public function create(string $name, float $density, RiskClass $riskClass): Result;

    /**
     * Builds the updated state of an existing product, re-validating input.
     *
     * @return Result<IProduct> Failure (422) on invalid input.
     */
    public function update(string $id, string $name, float $density, RiskClass $riskClass): Result;
}
