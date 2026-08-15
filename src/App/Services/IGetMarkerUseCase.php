<?php

/**
 * Get Marker Use Case Contract.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App\Services;

use App\Queries\Marker\GetMarkerQuery;
use Shared\Exceptions\Result;

/**
 * Reads the flag currently held for a value.
 *
 * The read side of {@see ISetMarkerUseCase}, and unguarded for the same reason:
 * knowing the value *is* the authorization.
 *
 * @see GetMarkerQuery What it takes.
 * @see \App\Services\Interno\GetMarkerUseCase The implementation.
 * @see ISetMarkerUseCase The write side, and the transition table.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IGetMarkerUseCase
{
    /**
     * Reads the flag currently held for a value.
     *
     * `true` is the only answer that means "we issued this and it is still
     * live". `false` (consumed) and a 404 (expired, or never existed) are
     * different histories with the same consequence, and a caller should treat
     * them the same — telling them apart would leak whether a value was ever
     * valid.
     *
     * @param  GetMarkerQuery  $query  The group and the value to look up.
     * @return Result<bool> The flag. A 404 when no live marker matched and a 404
     *                      when the group is not registered — deliberately the
     *                      same code, since a caller must not learn which it was;
     *                      422 when the value is empty.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function execute(GetMarkerQuery $query): Result;
}
