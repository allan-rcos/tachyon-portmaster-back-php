<?php

namespace Domain\TableModules;


use Domain\Models\Internal\Role;
use Domain\Ports\Core\IIntIdGenerator;
use Domain\Ports\TableModules\IRoleTM;
use Ds\Map;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

readonly final class RoleTM implements IRoleTM
{
    private const int MAX_NAME_LENGTH = 255;

    public function __construct(
        private IIntIdGenerator $idGenerator,
    ) {
    }

    public function create(
        string $name,
        array $permissions,
    ): Result {
        $errors = $this->validate(
            name: $name,
        );

        if (!$errors->isEmpty()) {
            $leaf = new LeafContext(
                "Validation errors",
                $errors,
                422,
            );
            return Result::failure(Leaf::newError($leaf));
        }

        return Result::success(new Role(
            id: $this->idGenerator->generate(),
            name: $name,
            permissions: $permissions,
        ));
    }

    private function validate(string $name): Map
    {
        $errors = new Map();

        if (empty($name)) {
            $errors->put('name', 'Name is required.');
        } elseif (strlen($name) > self::MAX_NAME_LENGTH) {
            $errors->put('name',
                "Name must not exceed ".self::MAX_NAME_LENGTH." characters.");
        }

        return $errors;
    }
}