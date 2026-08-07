<?php

/**
 * Role List Response Message.
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

use API\Negociation\DTO\Account\RoleXResponse;

/**
 * One page of roles.
 *
 * The row type is the account feature's {@see RoleXResponse}: a role reads the
 * same whether the caller is looking at their own profile or at the admin
 * listing, and the schema says so by reusing the table.
 *
 * @see RoleListXResponseFactory What renders this onto the wire.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class RoleListXResponse
{
    /**
     * @param  list<RoleXResponse>  $data  The rows of this page.
     * @param  ?string  $nextCursor  Opaque cursor for the next page, null on the last.
     * @param  int  $total  How many rows the whole query matches.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public array $data = [],
        public ?string $nextCursor = null,
        public int $total = 0,
    ) {
    }
}
