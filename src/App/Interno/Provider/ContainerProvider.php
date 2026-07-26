<?php

declare(strict_types=1);

namespace App\Interno\Provider;

use App\Services\ICreateContainerUseCase;
use App\Services\IDeleteContainerUseCase;
use App\Services\IDispatchContainerUseCase;
use App\Services\IGetContainerUseCase;
use App\Services\IListContainerSummariesUseCase;
use App\Services\IListContainersUseCase;
use App\Services\Interno\CreateContainerUseCase;
use App\Services\Interno\DeleteContainerUseCase;
use App\Services\Interno\DispatchContainerUseCase;
use App\Services\Interno\GetContainerUseCase;
use App\Services\Interno\ListContainerSummariesUseCase;
use App\Services\Interno\ListContainersUseCase;
use App\Services\Interno\SealContainerUseCase;
use App\Services\Interno\UpdateContainerUseCase;
use App\Services\ISealContainerUseCase;
use App\Services\IUpdateContainerUseCase;

final class ContainerProvider extends FeatureProvider
{
    public function listContainersUseCase(): IListContainersUseCase
    {
        return new ListContainersUseCase($this->infra->queryRepository(), $this->registrar());
    }

    public function listContainerSummariesUseCase(): IListContainerSummariesUseCase
    {
        return new ListContainerSummariesUseCase($this->infra->queryRepository(), $this->registrar());
    }

    public function getContainerUseCase(): IGetContainerUseCase
    {
        return new GetContainerUseCase($this->infra->queryRepository(), $this->registrar());
    }

    public function createContainerUseCase(): ICreateContainerUseCase
    {
        return new CreateContainerUseCase(
            $this->infra->unitOfWork(),
            $this->infra->containerRepository(),
            $this->domain->containerTM(),
            $this->registrar(),
        );
    }

    public function updateContainerUseCase(): IUpdateContainerUseCase
    {
        return new UpdateContainerUseCase(
            $this->infra->unitOfWork(),
            $this->infra->containerRepository(),
            $this->domain->containerTM(),
            $this->registrar(),
        );
    }

    public function sealContainerUseCase(): ISealContainerUseCase
    {
        return new SealContainerUseCase(
            $this->infra->unitOfWork(),
            $this->infra->containerRepository(),
            $this->domain->containerTM(),
            $this->registrar(),
        );
    }

    public function dispatchContainerUseCase(): IDispatchContainerUseCase
    {
        return new DispatchContainerUseCase(
            $this->infra->unitOfWork(),
            $this->infra->containerRepository(),
            $this->domain->containerTM(),
            $this->registrar(),
        );
    }

    public function deleteContainerUseCase(): IDeleteContainerUseCase
    {
        return new DeleteContainerUseCase(
            $this->infra->unitOfWork(),
            $this->infra->containerRepository(),
            $this->registrar(),
        );
    }
}
