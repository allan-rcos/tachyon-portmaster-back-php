<?php

declare(strict_types=1);

namespace App\Commands\User;

use App\Context\UserContext;

final readonly class CreateUserCommand
{
    /**
     * @param  list<string>  $roleIds
     */
    public function __construct(
        public UserContext $context,
        public string $name,
        public string $email,
        public string $initialPassword,
        /** @var list<string> Base62 role ids. */
        public array $roleIds,
    ) {
    }
}
