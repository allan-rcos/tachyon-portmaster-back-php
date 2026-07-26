<?php

declare(strict_types=1);

namespace App\Services\Interno;

use App\Commands\Container\DeleteContainerCommand;
use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Services\IDeleteContainerUseCase;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IContainerRepository;
use Shared\Exceptions\Result;

final readonly class DeleteContainerUseCase implements IDeleteContainerUseCase
{
    use AuthorizesWithPermission;

    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IContainerRepository $containers,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'container:delete');
    }

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

        return Result::void();
    }
}
