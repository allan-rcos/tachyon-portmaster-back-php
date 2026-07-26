<?php

declare(strict_types=1);

namespace App\Commands\User;

use App\Context\UserContext;

final readonly class UpdateUserRolesCommand
{
    /**
     * @param  list<string>  $roleIds
     */
    public function __construct(
        public UserContext $context,
        public string $id,
        /** @var list<string> Base62 role ids. */
        public array $roleIds,
    ) {
    }
}
