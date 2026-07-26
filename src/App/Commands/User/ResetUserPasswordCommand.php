<?php

declare(strict_types=1);

namespace App\Commands\User;

use App\Context\UserContext;

final readonly class ResetUserPasswordCommand
{
    public function __construct(
        public UserContext $context,
        public string $id,
        public string $newPassword,
    ) {
    }
}
