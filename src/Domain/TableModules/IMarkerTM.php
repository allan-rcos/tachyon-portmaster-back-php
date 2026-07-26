<?php

declare(strict_types=1);

namespace Domain\TableModules;

use Domain\Models\IMarker;
use Shared\Exceptions\Result;

interface IMarkerTM
{
    /**
     * Builds a marker by hashing the caller's plain value into the key it will
     * be stored under.
     *
     * This is the **only** place the plain value is seen. Everything downstream
     * — the use case, the repository, the table — handles the digest, so a value
     * flagged here cannot be read back out of the system.
     *
     * The value is deliberately typed as a bare string with no name attached to
     * its purpose: markers know nothing about tokens, sessions or whatever the
     * caller is flagging.
     *
     * @param  string  $group  Slug of a registered {@see \Domain\Models\IMarkerGroup}.
     * @param  string  $plain  The value to flag. Must be unguessable on its own.
     * @param  bool  $flag  The flag to carry — `true` to mark live, `false` to consume.
     * @return Result<IMarker> Failure 422 when the group slug is malformed or
     *                         the value is empty.
     */
    public function create(string $group, string $plain, bool $flag): Result;
}
