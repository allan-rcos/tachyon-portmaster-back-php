<?php

/**
 * Seal Container Use Case.
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

use App\Commands\Container\SealContainerCommand;
use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Services\ISealContainerUseCase;
use Domain\Models\IContainer;
use Domain\TableModules\IContainerTM;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Infra\Repository\IContainerRepository;
use Shared\Exceptions\Result;

/**
 * Seals a container, if the caller may and the domain allows the move.
 *
 * **The shape both transition use cases follow**, a variant of the update shape
 * on {@see UpdateProductUseCase}: load the container, ask the table module for
 * the moved one, persist, commit.
 *
 * The table module returns a *new* container rather than mutating the loaded
 * one, so a refused transition leaves the object this use case holds untouched —
 * which is why the 409 path needs nothing undone beyond the rollback.
 *
 * @see ISealContainerUseCase The contract this implements.
 * @see DispatchContainerUseCase The other transition, identically shaped.
 * @uses IUnitOfWork The boundary this opens and closes.
 * @uses IContainerRepository Loads the container and persists the moved one.
 * @uses IContainerTM Decides whether the move is legal.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class SealContainerUseCase implements ISealContainerUseCase
{
    use AuthorizesWithPermission;

    /**
     * Declares `container:seal`, its own — sealing and dispatching are separate
     * privileges.
     *
     * @param  IUnitOfWork  $unitOfWork  The boundary; never the connection.
     * @param  IViewCacheRepository  $views  Told to drop the group once the
     *                                       commit has landed.
     * @param  IContainerRepository  $containers  Read from and written to.
     * @param  IContainerTM  $containerTM  Decides whether the container may be
     *                                     sealed from where it is.
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
        $this->permission = $this->declarePermission($registrar, 'container:seal');
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function execute(SealContainerCommand $command): Result
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

        $sealed = $this->containerTM->seal($container);
        if (!$sealed->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($sealed->getErrorId());
        }

        /** @var IContainer $updated */
        $updated = $sealed->getValue();

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

        return Result::void();
    }
}
