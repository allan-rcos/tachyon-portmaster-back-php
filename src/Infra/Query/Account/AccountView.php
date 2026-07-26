<?php

declare(strict_types=1);

namespace Infra\Query\Account;

use Infra\Query\Role\RoleViewItem;

/**
 * The authenticated user's profile read model: their data plus their roles
 * (each with permissions and user_count).
 */
final readonly class AccountView
{
    /**
     * @param  list<RoleViewItem>  $roles
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public array $roles,
    ) {
    }
}
