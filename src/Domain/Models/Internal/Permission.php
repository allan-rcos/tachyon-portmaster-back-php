<?php

/**
 * Permission.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Domain\Models\Internal;

use Domain\Models\IPermission;

/**
 * Concrete {@see IPermission}. Built only by
 * {@see \Domain\TableModules\IPermissionTM}, which validates it first.
 *
 * Plain promoted properties satisfy the interface's `{ get; }` hooks, so the
 * class can stay `readonly` — a hooked property cannot.
 *
 * @see IPermission What each property means.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class Permission implements IPermission
{
    /**
     * @param  string  $slug  The `domain:action` identifier.
     * @param  int  $id  Registry index; zero until the registry assigns one.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public string $slug,
        public int $id = 0,
    ) {
    }

    /**
     * A copy carrying the registry index.
     *
     * @param  int  $id  The index the registry assigned.
     * @return IPermission A new instance; this one is unchanged.
     *
     * @copyright 2026 Tachyon
     */
    public function withId(int $id): IPermission
    {
        return new self($this->slug, $id);
    }
}
