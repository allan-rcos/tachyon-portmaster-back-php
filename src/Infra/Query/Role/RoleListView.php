<?php

declare(strict_types=1);

namespace Infra\Query\Role;

use Ds\Seq;

final readonly class RoleListView
{
    /**
     * @param  Seq<RoleViewItem>  $items
     */
    public function __construct(
        public Seq $items,
        public ?string $nextCursor,
        public int $total,
    ) {
    }
}
