<?php

/**
 * Role View Item.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra\Query\Role;

/**
 * A single role read record, including the denormalized user count computed by
 * the DQL (a read-side aggregate, not on the domain model).
 *
 * `$userCount` is why this exists rather than the domain model being returned:
 * how many people hold a role is a fact about the pivot table, and putting it on
 * {@see \Domain\Models\IRole} would mean a count nobody asked for on every
 * write.
 *
 * @see RoleListView The paged result these make up.
 * @see \Infra\Query\Account\AccountView Carries these alongside a user profile.
 * @see \Infra\Query\Interno\GetRoleDQL What builds a single one.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class RoleViewItem
{
    /**
     * @param  string  $id  Base62, already encoded for the caller.
     * @param  string  $name  Display name.
     * @param  int  $userCount  How many users hold this role, counted across the
     *                          `user_roles` pivot at query time.
     * @param  list<string>  $permissions  Permission slugs in `domain:action`
     *                                     form, as stored on the role.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public string $id,
        public string $name,
        public int $userCount,
        public array $permissions,
    ) {
    }
}
