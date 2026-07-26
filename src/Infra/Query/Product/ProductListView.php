<?php

declare(strict_types=1);

namespace Infra\Query\Product;

use Ds\Seq;

/**
 * The result set of a product listing: the page items (ext-ds {@see Seq}) plus
 * the opaque continuation cursor and the total matching count.
 */
final readonly class ProductListView
{
    /**
     * @param  Seq<ProductViewItem>  $items
     */
    public function __construct(
        public Seq $items,
        public ?string $nextCursor,
        public int $total,
    ) {
    }
}
