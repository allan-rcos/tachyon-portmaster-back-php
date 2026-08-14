<?php

/**
 * Delete Container Use Case.
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

use App\Commands\Container\DeleteContainerCommand;
use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Services\IDeleteContainerUseCase;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Infra\Repository\IContainerRepository;
use Shared\Exceptions\Result;

/**
 * Removes a container, if the caller may.
 *
 * Follows the delete shape documented on {@see DeleteProductUseCase}: load so an
 * absent container is a 404, then delete. The manifest and telemetry go with it
 * by cascade, so nothing here clears them first.
 *
 * @see IDeleteContainerUseCase The contract this implements.
 * @see DeleteProductUseCase The shape.
 * @uses IUnitOfWork The boundary this opens and closes.
 * @uses IContainerRepository Loads the container, then removes it.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class DeleteContainerUseCase implements IDeleteContainerUseCase
{
    use AuthorizesWithPermission;

    /**
     * Declares `container:delete`. Takes no table module — there is no rule to
     * consult about a removal.
     *
     * @param  IUnitOfWork  $unitOfWork  The boundary; never the connection.
     * @param  IViewCacheRepository  $views  Told to drop the group once the
     *                                       commit has landed.
     * @param  IContainerRepository  $containers  Read from, then deleted from.
     * @param  IRegisterPermissionUseCase  $registrar  Consulted once, here.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IViewCacheRepository $views,
        private IContainerRepository $containers,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'container:delete');
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function execute(DeleteContainerCommand $command): Result
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

        $deleted = $this->containers->delete($command->id);
        if (!$deleted->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($deleted->getErrorId());
        }

        $commit = $this->unitOfWork->commit();
        if (!$commit->isSuccess()) {
            return Result::failure($commit->getErrorId());
        }

        // After the commit, never before: a read in between would repopulate
        // the cache from the state this write replaces.
        $this->views->invalidate(ViewCacheGroup::Container);

        return Result::void();
    }
}
