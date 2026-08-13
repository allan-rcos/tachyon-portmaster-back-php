<?php

/**
 * Create Product Use Case.
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

use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Commands\Product\CreateProductCommand;
use App\Services\ICreateProductUseCase;
use Domain\Models\IProduct;
use Domain\TableModules\IProductTM;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IProductRepository;
use Shared\Exceptions\Result;

/**
 * Creates a product, if the caller may and the domain allows it.
 *
 * **The shape every write use case in this layer follows**, written out once
 * here so the other thirty-eight need only say how they differ:
 *
 *  1. **authorize** — the first thing, before any resource is touched. A refused
 *     caller costs no transaction;
 *  2. **begin** the unit of work;
 *  3. **build** through the table module, which is where validation lives. This
 *     layer never checks a business rule itself;
 *  4. **persist** through the repository, which never validates;
 *  5. **commit**.
 *
 * **Every failure path after step 2 rolls back**, and hands the original
 * `Leaf` id straight back rather than wrapping it — so a 422 from the table
 * module reaches the caller as a 422, and this layer invents no status codes of
 * its own beyond the 403 the trait produces.
 *
 * A failed `commit()` does not roll back: there is no longer a boundary to
 * abandon, and the session has already released it.
 *
 * Note what this class does *not* do: no SQL, no hashing, no id generation, no
 * validation. It sequences collaborators and owns the transaction, and that is
 * the whole job.
 *
 * @see ICreateProductUseCase The contract this implements.
 * @see AuthorizesWithPermission Where the 403 and the boot-time declaration come from.
 * @uses IUnitOfWork The boundary this opens and closes.
 * @uses IProductRepository Persists what the table module built.
 * @uses IProductTM Validates and builds; the only place a rule is decided.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class CreateProductUseCase implements ICreateProductUseCase
{
    use AuthorizesWithPermission;

    /**
     * Declares `product:create` as it is constructed, which is what puts the
     * permission in the registry at WorkerStart without any list to maintain.
     *
     * @param  IUnitOfWork  $unitOfWork  The boundary; never the connection.
     * @param  IProductRepository  $products  Where the built product is written.
     * @param  IProductTM  $productTM  Validates and builds it.
     * @param  IRegisterPermissionUseCase  $registrar  Consulted once, here, and
     *                                                 not retained — only the
     *                                                 slug it returns is.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IProductRepository $products,
        private IProductTM $productTM,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'product:create');
    }

    /**
     * Runs the five steps above.
     *
     * @param  CreateProductCommand  $command  Carries the caller and the
     *                                         product's fields.
     * @return Result<IProduct> The created product on success. A 403 when the
     *                          caller lacks `product:create`, a 422 when the
     *                          table module refused the fields, a 500 when the
     *                          boundary or the write failed — each passed
     *                          through from wherever it arose.
     *
     * @copyright 2026 Tachyon
     */
    public function execute(CreateProductCommand $command): Result
    {
        $denied = $this->authorize($command->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        $begin = $this->unitOfWork->begin();
        if (!$begin->isSuccess()) {
            return Result::failure($begin->getErrorId());
        }

        $built = $this->productTM->create($command->name, $command->density,
            $command->riskClass);
        if (!$built->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($built->getErrorId());
        }

        /** @var IProduct $product */
        $product = $built->getValue();

        $inserted = $this->products->insert($product);
        if (!$inserted->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($inserted->getErrorId());
        }

        $commit = $this->unitOfWork->commit();
        if (!$commit->isSuccess()) {
            return Result::failure($commit->getErrorId());
        }

        return Result::success($product);
    }
}
