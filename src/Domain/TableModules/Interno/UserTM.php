<?php

namespace Domain\TableModules\Interno;

use Domain\Models\Internal\User;
use Domain\Models\IUser;
use Domain\Security\ISecureHasher;
use Domain\ID\IDatabaseIdGenerator;
use Domain\TableModules\IUserTM;
use Ds\Map;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

readonly final class UserTM implements IUserTM
{
    private const int MAX_NAME_LENGTH = 255;
    private const int MAX_EMAIL_LENGTH = 255;

    public function __construct(
        private IDatabaseIdGenerator $idGenerator,
        private ISecureHasher $passwordHasher,
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
        $errors = $this->validateProfile($name, $email);
        $this->validatePassword($password, $errors);

        if (!$errors->isEmpty()) {
            return Result::failure(Leaf::newError(new LeafContext('Validation errors', $errors, 422)));
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
     * @inheritDoc
     */
    public function update(IUser $user, string $name, string $email): Result
    {
        $errors = $this->validateProfile($name, $email);
        if (!$errors->isEmpty()) {
            return Result::failure(Leaf::newError(new LeafContext('Validation errors', $errors, 422)));
        }

        return Result::success(new User(
            id: $user->id,
            name: $name,
            email: $email,
            passwordHash: $user->passwordHash,
            roles: $user->roles,
        ));
    }

    /**
     * @inheritDoc
     */
    public function changePassword(IUser $user, string $newPassword): Result
    {
        return $this->withPassword($user, $newPassword);
    }

    /**
     * @inheritDoc
     */
    public function resetPassword(IUser $user, string $newPassword): Result
    {
        return $this->withPassword($user, $newPassword);
    }

    /**
     * @return Result<IUser>
     */
    private function withPassword(IUser $user, string $newPassword): Result
    {
        /** @var Map<string, string> $errors */
        $errors = new Map();
        $this->validatePassword($newPassword, $errors);

        if (!$errors->isEmpty()) {
            return Result::failure(Leaf::newError(new LeafContext('Validation errors', $errors, 422)));
        }

        return Result::success(new User(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            passwordHash: $this->passwordHasher->hash($newPassword),
            roles: $user->roles,
        ));
    }

    /**
     * @return Map<string, string>
     */
    private function validateProfile(string $name, string $email): Map
    {
        /** @var Map<string, string> $errors */
        $errors = new Map();

        if (empty($name)) {
            $errors->put('name', 'Name is required');
        } elseif (strlen($name) > self::MAX_NAME_LENGTH) {
            $errors->put('name', 'Name must not exceed ' . self::MAX_NAME_LENGTH . ' characters');
        }

        if (empty($email)) {
            $errors->put('email', 'Email is required');
        } elseif (strlen($email) > self::MAX_EMAIL_LENGTH) {
            $errors->put('email', 'Email must not exceed ' . self::MAX_EMAIL_LENGTH . ' characters');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors->put('email', 'Invalid email format');
        }

        return $errors;
    }

    /**
     * @param  Map<string, string>  $errors
     */
    private function validatePassword(string $password, Map $errors): void
    {
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
            $errors->put(
                'password',
                'Password must be at least 8 characters long and include uppercase, lowercase letters, and numbers',
            );
        }
    }
}
