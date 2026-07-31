<?php

/**
 * Get Product Query.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App\Queries\Product;

use App\Context\UserContext;

/**
 * Reads one product by id.
 *
 * Follows the query shape documented on {@see ListProductsQuery}, reduced to its
 * smallest form: the caller and one identifier.
 *
 * @see \App\Services\IGetProductUseCase What consumes it.
 * @see ListProductsQuery The shape, and the paged sibling.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class GetProductQuery
{
    /**
     * @param  UserContext  $context  The caller, established at the edge from
     *                                their session.
     * @param  string  $id  Base62 id; an unparseable one becomes a failure
     *                      further down, not here.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public UserContext $context,
        public string $id,
    ) {
    }
}
