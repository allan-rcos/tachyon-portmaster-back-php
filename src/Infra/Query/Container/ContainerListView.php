<?php

declare(strict_types=1);

namespace Infra\Query\Container;

use Ds\Seq;

final readonly class ContainerListView
{
    /**
     * @param  Seq<ContainerViewItem>  $items
     */
    public function __construct(
        public Seq $items,
        public ?string $nextCursor,
        public int $total,
    ) {
    }
}
