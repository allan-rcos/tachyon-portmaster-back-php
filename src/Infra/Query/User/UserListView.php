<?php

declare(strict_types=1);

namespace Infra\Query\User;

use Infra\Query\Account\AccountView;

/**
 * `GET /users` returns a bare array (no envelope, no cursor/total), so this
 * simply carries the page of user profiles.
 */
final readonly class UserListView
{
    /**
     * @param  list<AccountView>  $items
     */
    public function __construct(
        public array $items,
    ) {
    }
}
