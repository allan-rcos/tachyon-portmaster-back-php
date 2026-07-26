<?php

declare(strict_types=1);

namespace Domain\Models\Internal;

use Domain\Models\IPermission;

/**
 * Concrete {@see IPermission}. Built only by
 * {@see \Domain\TableModules\IPermissionTM}, which validates it first.
 *
 * Plain promoted properties satisfy the interface's `{ get; }` hooks, so the
 * class can stay `readonly` — a hooked property cannot.
 */
final readonly class Permission implements IPermission
{
    public function __construct(
        public string $slug,
        public int $id = 0,
    ) {
    }

    public function withId(int $id): IPermission
    {
        return new self($this->slug, $id);
    }
}
