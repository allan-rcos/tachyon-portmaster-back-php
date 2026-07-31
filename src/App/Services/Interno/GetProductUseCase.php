<?php

/**
 * Get Product Use Case.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App\Services\Interno;

use App\Queries\Product\GetProductQuery;
use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Services\IGetProductUseCase;
use Infra\Query\Interno\GetProductDQL;
use Infra\Query\IQueryRepository;
use Infra\Query\Product\ProductViewItem;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

/**
 * Reads one product, if the caller may.
 *
 * **The shape every single-read use case in this layer follows**: authorize, run
 * the DQL, and turn a null view into a 404.
 *
 * That last step is the whole reason this class is not just a pass-through. The
 * query layer treats an empty result as a success carrying null — deciding that
 * "no such product" is an error is an application judgement, so it is made here
 * rather than in {@see IQueryRepository}. A list read
 * ({@see ListProductsUseCase}) makes the opposite judgement about the same
 * emptiness.
 *
 * No unit of work: a read opens no boundary.
 *
 * @see IGetProductUseCase The contract this implements.
 * @see ListProductsUseCase The list shape, which does not 404.
 * @uses IQueryRepository Runs the query; this layer never builds SQL.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class GetProductUseCase implements IGetProductUseCase
{
    use AuthorizesWithPermission;

    /**
     * Declares `product:read`, the same slug {@see ListProductsUseCase}
     * declares.
     *
     * @param  IQueryRepository  $queries  The read-side runner.
     * @param  IRegisterPermissionUseCase  $registrar  Consulted once, here, and
     *                                                 not retained.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IQueryRepository $queries,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'product:read');
    }

    /**
     * Authorizes, reads, and rejects an absent product.
     *
     * The absence is detected with an `instanceof` rather than a null check, so
     * a view of an unexpected type is refused the same way — the caller is never
     * handed something that is not a product.
     *
     * @param  GetProductQuery  $query  Carries the caller and the id.
     * @return Result<ProductViewItem> The product; a 403 when the caller lacks
     *                                 `product:read`, a 404 when no product has
     *                                 that id, a 500 when the read failed.
     *
     * @copyright 2026 Tachyon
     */
    public function execute(GetProductQuery $query): Result
    {
        $denied = $this->authorize($query->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        $result = $this->queries->run(new GetProductDQL($query->id));
        if (!$result->isSuccess()) {
            return Result::failure($result->getErrorId());
        }

        $item = $result->getValue();
        if (!$item instanceof ProductViewItem) {
            return Result::failure(Leaf::newError(new LeafContext(
                message: "Product with id $query->id not found",
                code: 404,
            )));
        }

        return Result::success($item);
    }
}
