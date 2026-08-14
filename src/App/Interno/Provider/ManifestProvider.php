<?php

/**
 * Manifest Provider.
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

use App\Services\Interno\LoadItemUseCase;
use App\Services\Interno\UnloadItemUseCase;
use App\Services\ILoadItemUseCase;
use App\Services\IUnloadItemUseCase;

/**
 * Builds the load and unload use cases.
 *
 * Kept apart from {@see ContainerProvider} because changing what a container
 * carries is a different privilege from changing the container itself, and the
 * two use cases here need three repositories where the container ones need one.
 *
 * See {@see FeatureProvider} for why the wiring is split this way and why
 * nothing here is memoized.
 *
 * @see FeatureProvider The base, and the registrar.
 * @see ContainerProvider The container itself.
 * @see \App\Interno\AppProvider What re-exports these.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final class ManifestProvider extends FeatureProvider
{
    /**
     * Builds the {@see ILoadItemUseCase} implementation.
     *
     * @copyright 2026 Tachyon
     */
    public function loadItemUseCase(): ILoadItemUseCase
    {
        return new LoadItemUseCase(
            $this->infra->unitOfWork(),
            $this->infra->viewCacheRepository(),
            $this->infra->containerRepository(),
            $this->infra->productRepository(),
            $this->infra->manifestRepository(),
            $this->domain->manifestTM(),
            $this->registrar(),
        );
    }

    /**
     * Builds the {@see IUnloadItemUseCase} implementation.
     *
     * @copyright 2026 Tachyon
     */
    public function unloadItemUseCase(): IUnloadItemUseCase
    {
        return new UnloadItemUseCase(
            $this->infra->unitOfWork(),
            $this->infra->viewCacheRepository(),
            $this->infra->containerRepository(),
            $this->infra->productRepository(),
            $this->infra->manifestRepository(),
            $this->domain->manifestTM(),
            $this->registrar(),
        );
    }
}
