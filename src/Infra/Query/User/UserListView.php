<?php

/**
 * User List View.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra\Query\User;

use Infra\Query\Account\AccountView;

/**
 * `GET /users` returns a bare array (no envelope, no cursor/total), so this
 * simply carries the page of user profiles.
 *
 * The odd one out among the list views: no cursor and no total, because the
 * endpoint has none to give. It reuses {@see AccountView} rather than defining a
 * slimmer item, so a user listed here carries the same roles a user fetched
 * alone would.
 *
 * @see AccountView What the list is made of.
 * @see \Infra\Query\Interno\ListUsersDQL What builds one.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class UserListView
{
    /**
     * @param  list<AccountView>  $items  Every user the query matched, each with
     *                                    their roles already attached.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public array $items,
    ) {
    }
}
