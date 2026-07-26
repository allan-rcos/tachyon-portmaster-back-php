<?php

namespace Domain\TableModules\Interno;

use Domain\Enums\RiskClass;
use Domain\Models\Internal\Product;
use Domain\TableModules\IProductTM;
use Domain\ID\IDatabaseIdGenerator;
use Ds\Map;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

readonly final class ProductTM implements IProductTM
{
    private const int MAX_NAME_LENGTH = 255;

    public function __construct(
        private IDatabaseIdGenerator $idGenerator,
    ) {
    }

    public function create(string $name, float $density, RiskClass $riskClass): Result
    {
        $errors = $this->validate($name, $density);
        if (!$errors->isEmpty()) {
            return Result::failure(Leaf::newError(new LeafContext('Validation errors', $errors, 422)));
        }

        return Result::success(new Product(
            id: $this->idGenerator->generate(),
            name: $name,
            density: $density,
            riskClass: $riskClass,
        ));
    }

    public function update(string $id, string $name, float $density, RiskClass $riskClass): Result
    {
        $errors = $this->validate($name, $density);
        if (!$errors->isEmpty()) {
            return Result::failure(Leaf::newError(new LeafContext('Validation errors', $errors, 422)));
        }

        return Result::success(new Product(
            id: $id,
            name: $name,
            density: $density,
            riskClass: $riskClass,
        ));
    }

    /**
     * @return Map<string, string>
     */
    private function validate(string $name, float $density): Map
    {
        /** @var Map<string, string> $errors */
        $errors = new Map();

        $trimmed = trim($name);
        if ($trimmed === '') {
            $errors->put('name', 'Name is required.');
        } elseif (strlen($name) > self::MAX_NAME_LENGTH) {
            $errors->put('name', 'Name must not exceed ' . self::MAX_NAME_LENGTH . ' characters.');
        }

        if ($density <= 0) {
            $errors->put('density', 'Density must be greater than zero.');
        }

        return $errors;
    }
}
