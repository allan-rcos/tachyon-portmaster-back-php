<?php

/**
 * Container Summary List View.
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
 * A container listing with each container's manifest and recent telemetry
 * attached.
 *
 * The expensive sibling of {@see ContainerListView}: same paging, but every item
 * carries its cargo lines and log entries, so a caller rendering a dashboard
 * does not follow up with a request per container.
 *
 * @see ContainerSummaryViewItem What the page is made of.
 * @see \Infra\Query\Interno\ListContainerSummariesDQL What builds one.
 * @see \Infra\Query\Cursor What `$nextCursor` encodes.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class ContainerSummaryListView
{
    /**
     * @param  Seq<ContainerSummaryViewItem>  $items  This page, in the query's
     *                                                order.
     * @param  string|null  $nextCursor  Token for the following page, or null on
     *                                   the last one.
     * @param  int  $total  How many containers matched the filters overall —
     *                      containers, not cargo lines.
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
