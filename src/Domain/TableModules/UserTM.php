<?php

namespace Domain\TableModules;

use Domain\Models\Internal\User;
use Domain\Ports\Core\IHasher;
use Domain\Ports\Core\IIntIdGenerator;
use Domain\Ports\TableModules\IUserTM;
use Ds\Map;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

readonly final class UserTM implements IUserTM
{
    private const int MAX_NAME_LENGTH = 255;
    private const int MAX_EMAIL_LENGTH = 255;

    public function __construct(
        private IIntIdGenerator $idGenerator,
        private IHasher $passwordHasher,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function create(
        string $name,
        string $email,
        string $password,
        array $roles,
    ): Result {
        $errors = $this->validate(
            name: $name,
            email: $email,
            password: $password,
        );

        if (!$errors->isEmpty()) {
            $leaf = new LeafContext(
                "Validation errors",
                $errors,
                422,
            );
            return Result::failure(Leaf::newError($leaf));
        }

        return Result::success(new User(
            id: $this->idGenerator->generate(),
            name: $name,
            email: $email,
            passwordHash: $this->passwordHasher->hash($password),
            roles: $roles,
        ));
    }

    /**
     * Validates user input.
     * @param  string  $name
     * @param  string  $email
     * @param  string  $password
     * @return Map
     */
    public function validate(
        string $name,
        string $email,
        string $password,
    ): Map {
        $errors = new Map();
        if (empty($name)) {
            $errors->put('name', 'Name is required');
        } elseif (strlen($name) > self::MAX_NAME_LENGTH) {
            $errors->put('name',
                "Name must not exceed ".self::MAX_NAME_LENGTH." characters");
        }
        if (empty($email)) {
            $errors->put('email', 'Email is required');
        } elseif (strlen($email) > self::MAX_EMAIL_LENGTH) {
            $errors->put('email',
                "Email must not exceed ".self::MAX_EMAIL_LENGTH." characters");
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors->put('email', 'Invalid email format');
        }
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
            $errors->put('password',
                'Password must be at least 8 characters long and include uppercase, lowercase letters, and numbers');
        }
        return $errors;
    }
}