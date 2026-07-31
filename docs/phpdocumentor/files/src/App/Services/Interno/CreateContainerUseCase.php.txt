<?php

/**
 * Create Container Use Case.
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
use App\Commands\Container\CreateContainerCommand;
use App\Services\ICreateContainerUseCase;
use Domain\Models\IContainer;
use Domain\TableModules\IContainerTM;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IContainerRepository;
use Shared\Exceptions\Result;

/**
 * Creates an empty container, if the caller may and the domain allows it.
 *
 * Follows the write shape documented on {@see CreateProductUseCase}: authorize,
 * begin, build, persist, commit, rolling back on every failure after the
 * boundary opened.
 *
 * @see ICreateContainerUseCase The contract this implements.
 * @see CreateProductUseCase The shape.
 * @uses IUnitOfWork The boundary this opens and closes.
 * @uses IContainerRepository Persists what the table module built.
 * @uses IContainerTM Validates and builds; the only place a rule is decided.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class CreateContainerUseCase implements ICreateContainerUseCase
{
    use AuthorizesWithPermission;

    /**
     * Declares `container:create`.
     *
     * @param  IUnitOfWork  $unitOfWork  The boundary; never the connection.
     * @param  IContainerRepository  $containers  Where the built container is
     *                                            written.
     * @param  IContainerTM  $containerTM  Validates and builds it.
     * @param  IRegisterPermissionUseCase  $registrar  Consulted once, here.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IContainerRepository $containers,
        private IContainerTM $containerTM,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'container:create');
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function execute(CreateContainerCommand $command): Result
    {
        $denied = $this->authorize($command->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        $begin = $this->unitOfWork->begin();
        if (!$begin->isSuccess()) {
            return Result::failure($begin->getErrorId());
        }

        $built = $this->containerTM->create($command->code, $command->maxCapacity);
        if (!$built->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($built->getErrorId());
        }

        /** @var IContainer $container */
        $container = $built->getValue();

        $inserted = $this->containers->insert($container);
        if (!$inserted->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($inserted->getErrorId());
        }

        $commit = $this->unitOfWork->commit();
        if (!$commit->isSuccess()) {
            return Result::failure($commit->getErrorId());
        }

        return Result::success($container);
    }
}
