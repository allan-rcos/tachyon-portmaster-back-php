<?php

/**
 * Accepts Strategy Manager Contract.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation;

/**
 * Records which outbound strategy this request resolved to.
 *
 * The outbound twin of {@see IContentTypeStrategyManager}, and narrow for the
 * same reason: the middleware writes, everyone else reads.
 *
 * @see IAcceptsStrategy The reader side.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IAcceptsStrategyManager
{
    /**
     * Records the strategy for the current request.
     *
     * @param  IAcceptsStrategy  $strategy  Resolved from `Accept`.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function setStrategy(IAcceptsStrategy $strategy): void;
}
