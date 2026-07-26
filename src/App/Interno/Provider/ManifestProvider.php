<?php

declare(strict_types=1);

namespace App\Interno\Provider;

use App\Services\Interno\LoadItemUseCase;
use App\Services\Interno\UnloadItemUseCase;
use App\Services\ILoadItemUseCase;
use App\Services\IUnloadItemUseCase;

final class ManifestProvider extends FeatureProvider
{
    public function loadItemUseCase(): ILoadItemUseCase
    {
        return new LoadItemUseCase(
            $this->infra->unitOfWork(),
            $this->infra->containerRepository(),
            $this->infra->productRepository(),
            $this->infra->manifestRepository(),
            $this->domain->manifestTM(),
            $this->registrar(),
        );
    }

    public function unloadItemUseCase(): IUnloadItemUseCase
    {
        return new UnloadItemUseCase(
            $this->infra->unitOfWork(),
            $this->infra->containerRepository(),
            $this->infra->productRepository(),
            $this->infra->manifestRepository(),
            $this->domain->manifestTM(),
            $this->registrar(),
        );
    }
}
