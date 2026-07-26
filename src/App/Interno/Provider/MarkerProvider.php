<?php

declare(strict_types=1);

namespace App\Interno\Provider;

use App\Services\IGetMarkerUseCase;
use App\Services\Interno\GetMarkerUseCase;
use App\Services\Interno\RegisterMarkerGroupUseCase;
use App\Services\Interno\SetMarkerUseCase;
use App\Services\IRegisterMarkerGroupUseCase;
use App\Services\ISetMarkerUseCase;

/**
 * Markers: the flag store behind single-use values.
 *
 * None of these declare a permission. A marker operation is not something a
 * *user* does — the callers are the flows deciding whether there is a user at
 * all, and demanding a permission from an unauthenticated caller would make the
 * feature unusable for its only purpose.
 */
final class MarkerProvider extends FeatureProvider
{
    /**
     * The group registrar, handed to whichever feature declares a group in its
     * constructor at WorkerStart — the marker counterpart of
     * {@see FeatureProvider::registrar()}.
     */
    public function registerMarkerGroupUseCase(): IRegisterMarkerGroupUseCase
    {
        return new RegisterMarkerGroupUseCase(
            $this->domain->markerGroupTM(),
            $this->infra->markerGroupRepository(),
        );
    }

    public function setMarkerUseCase(): ISetMarkerUseCase
    {
        return new SetMarkerUseCase(
            $this->domain->markerTM(),
            $this->infra->markerRepository(),
        );
    }

    public function getMarkerUseCase(): IGetMarkerUseCase
    {
        return new GetMarkerUseCase(
            $this->domain->markerTM(),
            $this->infra->markerRepository(),
        );
    }
}
