<?php

/**
 * Metadata Provider.
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

use App\Services\IListPermissionsUseCase;
use App\Services\Interno\ListPermissionsUseCase;

/**
 * Builds the read side of the system metadata registries.
 *
 * The registry itself is filled from all over the codebase — every guarded use
 * case declares its own permission — so there is no single feature that owns it.
 * This provider only wires the listing that makes the resulting catalogue
 * readable.
 *
 * See {@see FeatureProvider} for why the wiring is split this way and why
 * nothing here is memoized.
 *
 * @see FeatureProvider The base, and the registrar.
 * @see RoleProvider Where the permission slugs listed here are granted.
 * @see \App\Interno\AppProvider What re-exports these.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final class MetadataProvider extends FeatureProvider
{
    /**
     * Builds the {@see IListPermissionsUseCase} implementation.
     *
     * @copyright 2026 Tachyon
     */
    public function listPermissionsUseCase(): IListPermissionsUseCase
    {
        return new ListPermissionsUseCase($this->infra->permissionRepository(), $this->registrar());
    }
}
