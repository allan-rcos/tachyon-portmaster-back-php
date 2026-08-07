<?php

/**
 * User Admin Password Reset Request Message.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation\DTO\Admin;

/**
 * An administrator setting another user's password.
 *
 * No current password: the administrator's own permission is the proof, which
 * is what makes this a different message from
 * {@see \API\Negociation\DTO\Account\AccountPasswordChangeXRequest}.
 *
 * @see UserAdminPasswordResetXRequestFactory What builds this from a request body.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class UserAdminPasswordResetXRequest
{
    /**
     * @param  ?string  $newPassword  The password to install.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ?string $newPassword = null,
    ) {
    }
}
