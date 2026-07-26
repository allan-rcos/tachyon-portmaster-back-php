<?php

declare(strict_types=1);

namespace App\Services\Interno;

use App\Commands\Container\DispatchContainerCommand;
use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Services\IDispatchContainerUseCase;
use Domain\Models\IContainer;
use Domain\TableModules\IContainerTM;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IContainerRepository;
use Shared\Exceptions\Result;

final readonly class DispatchContainerUseCase implements IDispatchContainerUseCase
{
    use AuthorizesWithPermission;

    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IContainerRepository $containers,
        private IContainerTM $containerTM,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'container:dispatch');
    }

    public function execute(DispatchContainerCommand $command): Result
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

        $dispatched = $this->containerTM->dispatch($container);
        if (!$dispatched->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($dispatched->getErrorId());
        }

        /** @var IContainer $updated */
        $updated = $dispatched->getValue();

        $persisted = $this->containers->update($updated);
        if (!$persisted->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($persisted->getErrorId());
        }

        $commit = $this->unitOfWork->commit();
        if (!$commit->isSuccess()) {
            return Result::failure($commit->getErrorId());
        }

        return Result::void();
    }
}
