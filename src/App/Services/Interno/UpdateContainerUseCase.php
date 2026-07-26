<?php

declare(strict_types=1);

namespace App\Services\Interno;

use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Commands\Container\UpdateContainerCommand;
use App\Services\IUpdateContainerUseCase;
use Domain\Models\IContainer;
use Domain\TableModules\IContainerTM;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IContainerRepository;
use Shared\Exceptions\Result;

final readonly class UpdateContainerUseCase implements IUpdateContainerUseCase
{
    use AuthorizesWithPermission;

    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IContainerRepository $containers,
        private IContainerTM $containerTM,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'container:update');
    }

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

        return Result::success($updated);
    }
}
