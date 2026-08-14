<?php

/**
 * Feature Provider.
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

use App\Services\Interno\RegisterPermissionUseCase;
use App\Events\IMetaEventStack;
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
 *
 * **Use cases are not memoized here**, unlike everything in
 * {@see \Infra\Interno\InfraProvider} and {@see \Domain\Interno\DomainProvider}:
 * each call builds a fresh one. They are stateless and cheap, and because
 * registration is idempotent by slug, re-declaring a permission on every
 * construction is a no-op rather than a duplicate.
 *
 * @see \App\Interno\AppProvider What composes the subclasses and re-exports them.
 * @uses IDomainProvider Supplies the table modules.
 * @uses IInfraProvider Supplies the repositories, the boundary and the registry.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
abstract class FeatureProvider
{
    /**
     * @var ?IRegisterPermissionUseCase Memoized registrar; null until first use.
     *                                  The one thing here that *is* memoized,
     *                                  and only per provider — what actually
     *                                  needs sharing is the registry it writes
     *                                  to, which the infra layer already
     *                                  memoizes per worker.
     */
    private ?IRegisterPermissionUseCase $registerPermission = null;

    /**
     * All three are held `protected`, so subclasses reach them directly rather
     * than through accessors.
     *
     * @param  IDomainProvider  $domain  Supplies the table modules.
     * @param  IInfraProvider  $infra  Supplies the repositories, the boundary and
     *                                 the permission registry.
     * @param  IMetaEventStack  $events  Handed to the read use cases, which
     *                                   report a cache hit on it. Shared with
     *                                   the middleware that reads it back —
     *                                   what scopes it to one request is the
     *                                   coroutine, not the instance.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        protected readonly IDomainProvider $domain,
        protected readonly IInfraProvider $infra,
        protected readonly IMetaEventStack $events,
    ) {
    }

    /**
     * The permission registrar handed to every guarded use case's constructor,
     * where it declares its own permission at WorkerStart.
     *
     * @return IRegisterPermissionUseCase Memoized per provider; every one of
     *                                    them writes into the same worker-wide
     *                                    registry.
     *
     * @copyright 2026 Tachyon
     */
    protected function registrar(): IRegisterPermissionUseCase
    {
        return $this->registerPermission ??= new RegisterPermissionUseCase(
            $this->domain->permissionTM(),
            $this->infra->permissionRepository(),
        );
    }
}
