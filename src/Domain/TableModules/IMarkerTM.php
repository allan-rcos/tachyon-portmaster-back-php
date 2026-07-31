<?php

/**
 * Marker Table Module Contract.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Domain\TableModules;

use Domain\Models\IMarker;
use Shared\Exceptions\Result;

/**
 * Turns a plain value into the marker that stands in for it.
 *
 * The hashing boundary of the whole marker feature: what goes in is a value,
 * what comes out is a digest, and no path exists back.
 *
 * @see IMarker What gets built.
 * @see \Domain\TableModules\Interno\MarkerTM The implementation.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
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
     * @param  string  $plain  The value to flag. Must be unguessable on its own —
     *                         the digest is a key, not protection.
     * @param  bool  $flag  `true` to mark live, `false` to consume.
     * @return Result<IMarker> A 422 failure when the group slug is malformed or
     *                         the value is empty.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function create(string $group, string $plain, bool $flag): Result;
}
