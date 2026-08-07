<?php

/**
 * Account Password Change Request Message.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation\DTO\Account;

/**
 * A caller changing their own password.
 *
 * @see AccountPasswordChangeXRequestFactory What builds this from a request body.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class AccountPasswordChangeXRequest
{
    /**
     * @param  ?string  $currentPassword  The password in force, to prove ownership.
     * @param  ?string  $newPassword  What to replace it with.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ?string $currentPassword = null,
        public ?string $newPassword = null,
    ) {
    }
}
