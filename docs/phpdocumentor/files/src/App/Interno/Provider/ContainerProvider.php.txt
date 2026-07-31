<?php

/**
 * Container Provider.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

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

/**
 * Builds the container feature's use cases.
 *
 * The widest of the feature slices: the plain reads, the summary read, the
 * writes, and the two status transitions. See {@see FeatureProvider} for why the
 * wiring is split this way and why nothing here is memoized.
 *
 * @see FeatureProvider The base, and the registrar.
 * @see ManifestProvider What a container carries is wired separately.
 * @see \App\Interno\AppProvider What re-exports these.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final class ContainerProvider extends FeatureProvider
{
    /**
     * Builds the {@see IListContainersUseCase} implementation.
     *
     * @copyright 2026 Tachyon
     */
    public function listContainersUseCase(): IListContainersUseCase
    {
        return new ListContainersUseCase($this->infra->queryRepository(), $this->registrar());
    }

    /**
     * Builds the {@see IListContainerSummariesUseCase} implementation.
     *
     * @copyright 2026 Tachyon
     */
    public function listContainerSummariesUseCase(): IListContainerSummariesUseCase
    {
        return new ListContainerSummariesUseCase($this->infra->queryRepository(), $this->registrar());
    }

    /**
     * Builds the {@see IGetContainerUseCase} implementation.
     *
     * @copyright 2026 Tachyon
     */
    public function getContainerUseCase(): IGetContainerUseCase
    {
        return new GetContainerUseCase($this->infra->queryRepository(), $this->registrar());
    }

    /**
     * Builds the {@see ICreateContainerUseCase} implementation.
     *
     * @copyright 2026 Tachyon
     */
    public function createContainerUseCase(): ICreateContainerUseCase
    {
        return new CreateContainerUseCase(
            $this->infra->unitOfWork(),
            $this->infra->containerRepository(),
            $this->domain->containerTM(),
            $this->registrar(),
        );
    }

    /**
     * Builds the {@see IUpdateContainerUseCase} implementation.
     *
     * @copyright 2026 Tachyon
     */
    public function updateContainerUseCase(): IUpdateContainerUseCase
    {
        return new UpdateContainerUseCase(
            $this->infra->unitOfWork(),
            $this->infra->containerRepository(),
            $this->domain->containerTM(),
            $this->registrar(),
        );
    }

    /**
     * Builds the {@see ISealContainerUseCase} implementation.
     *
     * @copyright 2026 Tachyon
     */
    public function sealContainerUseCase(): ISealContainerUseCase
    {
        return new SealContainerUseCase(
            $this->infra->unitOfWork(),
            $this->infra->containerRepository(),
            $this->domain->containerTM(),
            $this->registrar(),
        );
    }

    /**
     * Builds the {@see IDispatchContainerUseCase} implementation.
     *
     * @copyright 2026 Tachyon
     */
    public function dispatchContainerUseCase(): IDispatchContainerUseCase
    {
        return new DispatchContainerUseCase(
            $this->infra->unitOfWork(),
            $this->infra->containerRepository(),
            $this->domain->containerTM(),
            $this->registrar(),
        );
    }

    /**
     * Builds the {@see IDeleteContainerUseCase} implementation.
     *
     * @copyright 2026 Tachyon
     */
    public function deleteContainerUseCase(): IDeleteContainerUseCase
    {
        return new DeleteContainerUseCase(
            $this->infra->unitOfWork(),
            $this->infra->containerRepository(),
            $this->registrar(),
        );
    }
}
