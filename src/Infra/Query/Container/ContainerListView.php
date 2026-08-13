<?php

/**
 * Container List View.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra\Query\Container;

use Ds\Seq;

/**
 * The result set of a container listing: the page items (ext-ds {@see Seq}) plus
 * the opaque continuation cursor and the total matching count.
 *
 * The plain listing — containers without their cargo.
 * {@see ContainerSummaryListView} is the same page with each manifest attached.
 *
 * @see ContainerViewItem What the page is made of.
 * @see \Infra\Query\Interno\ListContainersDQL What builds one.
 * @see \Infra\Query\Cursor What `$nextCursor` encodes.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class ContainerListView
{
    /**
     * @param  Seq<ContainerViewItem>  $items  This page, in the query's order.
     * @param  string|null  $nextCursor  Token for the following page, or null on
     *                                   the last one.
     * @param  int  $total  How many rows matched the filters overall.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public Seq $items,
        public ?string $nextCursor,
        public int $total,
    ) {
    }
}
