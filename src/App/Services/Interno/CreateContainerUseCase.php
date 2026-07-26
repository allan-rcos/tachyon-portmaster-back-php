<?php

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

final readonly class CreateContainerUseCase implements ICreateContainerUseCase
{
    use AuthorizesWithPermission;

    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IContainerRepository $containers,
        private IContainerTM $containerTM,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'container:create');
    }

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
