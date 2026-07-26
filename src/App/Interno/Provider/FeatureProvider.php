<?php

declare(strict_types=1);

namespace App\Interno\Provider;

use App\Services\Interno\RegisterPermissionUseCase;
use App\Services\IRegisterPermissionUseCase;
use Domain\IDomainProvider;
use Infra\IInfraProvider;

/**
 * Shared base for the per-feature application providers.
 *
 * {@see \App\Interno\AppProvider} used to be one class building every use case;
 * it grew past the point of being readable, so it is split by feature and now
 * only re-exports these. Each subclass owns one slice of the surface and can be
 * read on its own.
 *
 * It also builds the permission registrar every guarded use case needs. The
 * registrar itself is stateless; what matters is that it writes into
 * {@see IInfraProvider::permissionRepository()}, which is memoized per worker —
 * so however many feature providers exist, they all fill the same registry.
 */
abstract class FeatureProvider
{
    private ?IRegisterPermissionUseCase $registerPermission = null;

    public function __construct(
        protected readonly IDomainProvider $domain,
        protected readonly IInfraProvider $infra,
    ) {
    }

    /**
     * The permission registrar handed to every guarded use case's constructor,
     * where it declares its own permission at WorkerStart.
     */
    protected function registrar(): IRegisterPermissionUseCase
    {
        return $this->registerPermission ??= new RegisterPermissionUseCase(
            $this->domain->permissionTM(),
            $this->infra->permissionRepository(),
        );
    }
}
