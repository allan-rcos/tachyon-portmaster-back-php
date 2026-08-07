<?php

/**
 * Content Type Strategy Manager Contract.
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
 * Records which inbound strategy this request resolved to.
 *
 * Deliberately one method wide. The middleware is the only writer in the
 * system, and this is all it is allowed to see — it never gets a handle it
 * could decode a body with, and the controllers never get a handle they could
 * re-negotiate with. The single object behind both interfaces is
 * {@see \API\Negociation\Interno\ContentTypeStrategyContext}, and only
 * {@see \API\Interno\ApiProvider} knows that.
 *
 * @see IContentTypeStrategy The reader side.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IContentTypeStrategyManager
{
    /**
     * Records the strategy for the current request.
     *
     * @param  IContentTypeStrategy  $strategy  Resolved from `Content-Type`.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function setStrategy(IContentTypeStrategy $strategy): void;
}
