<?php

declare(strict_types=1);

namespace Infra\Query\Role;

/**
 * A single role read record, including the denormalized user count computed by
 * the DQL (a read-side aggregate, not on the domain model).
 */
final readonly class RoleViewItem
{
    /**
     * @param  list<string>  $permissions  Permission slugs.
     */
    public function __construct(
        public string $id,
        public string $name,
        public int $userCount,
        public array $permissions,
    ) {
    }
}
