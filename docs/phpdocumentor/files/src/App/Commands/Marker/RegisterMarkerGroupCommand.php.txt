<?php

/**
 * Register Marker Group Command.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App\Commands\Marker;

/**
 * Declares one marker group the application layer needs.
 *
 * Like {@see \App\Commands\Permission\RegisterPermissionCommand} it carries no
 * {@see \App\Context\UserContext}: it runs at WorkerStart, before any request
 * exists, so there is no caller to authorize.
 *
 * @see \App\Services\IRegisterMarkerGroupUseCase What consumes it.
 * @see \App\Commands\Permission\RegisterPermissionCommand The same pattern, for permissions.
 * @see SetMarkerCommand What later files markers under a registered group.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class RegisterMarkerGroupCommand
{
    /**
     * @param  string  $slug  Name the feature declares the group under; the
     *                        whole of a group. A marker filed under an
     *                        unregistered slug is refused with a 404.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public string $slug,
    ) {
    }
}
