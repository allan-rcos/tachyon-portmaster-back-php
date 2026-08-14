<?php

/**
 * Update Container Use Case.
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
use App\Commands\Container\UpdateContainerCommand;
use App\Services\IUpdateContainerUseCase;
use Domain\Models\IContainer;
use Domain\TableModules\IContainerTM;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Infra\Repository\IContainerRepository;
use Shared\Exceptions\Result;

/**
 * Changes a container's capacity, if the caller may and the domain allows it.
 *
 * Follows the update shape documented on {@see UpdateProductUseCase}: the
 * existing container is loaded and handed to the table module, which is what
 * lets the domain refuse a capacity smaller than what the container already
 * carries.
 *
 * That is a difference from the product update worth noting: here the loaded
 * record is not merely a 404 check, it is an input to the rule.
 *
 * @see IUpdateContainerUseCase The contract this implements.
 * @see UpdateProductUseCase The shape.
 * @uses IUnitOfWork The boundary this opens and closes.
 * @uses IContainerRepository Loads the existing container and persists the new one.
 * @uses IContainerTM Validates and rebuilds it.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class UpdateContainerUseCase implements IUpdateContainerUseCase
{
    use AuthorizesWithPermission;

    /**
     * Declares `container:update`.
     *
     * @param  IUnitOfWork  $unitOfWork  The boundary; never the connection.
     * @param  IViewCacheRepository  $views  Told to drop the group once the
     *                                       commit has landed.
     * @param  IContainerRepository  $containers  Read from and written to.
     * @param  IContainerTM  $containerTM  Decides whether the new capacity is
     *                                     acceptable for this container.
     * @param  IRegisterPermissionUseCase  $registrar  Consulted once, here.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IViewCacheRepository $views,
        private IContainerRepository $containers,
        private IContainerTM $containerTM,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'container:update');
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function execute(UpdateContainerCommand $command): Result
    {
        $denied = $this->authorize($command->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        $begin = $this->unitOfWork->begin();
        if (!$begin->isSuccess()) {
            return Result::failure($begin->getErrorId());
        }

        $existing = $this->containers->findById($command->id);
        if (!$existing->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($existing->getErrorId());
        }

        /** @var IContainer $container */
        $container = $existing->getValue();

        $built = $this->containerTM->update($container, $command->maxCapacity);
        if (!$built->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($built->getErrorId());
        }

        /** @var IContainer $updated */
        $updated = $built->getValue();

        $persisted = $this->containers->update($updated);
        if (!$persisted->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($persisted->getErrorId());
        }

        $commit = $this->unitOfWork->commit();
        if (!$commit->isSuccess()) {
            return Result::failure($commit->getErrorId());
        }

        // After the commit, never before: a read in between would repopulate
        // the cache from the state this write replaces.
        $this->views->invalidate(ViewCacheGroup::Container);

        return Result::success($updated);
    }
}
