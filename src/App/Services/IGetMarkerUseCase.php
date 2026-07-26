<?php

declare(strict_types=1);

namespace App\Services;

use App\Queries\Marker\GetMarkerQuery;
use Shared\Exceptions\Result;

interface IGetMarkerUseCase
{
    /**
     * Reads the flag currently held for a value.
     *
     * `true` is the only answer that means "we issued this and it is still
     * live". `false` (consumed) and `null` (expired, or never existed) are
     * different histories with the same consequence, and a caller should treat
     * them the same — telling them apart would leak whether a value was ever
     * valid.
     *
     * @return Result<bool|null> 404 when the group is not registered, 422 when
     *                           the value is empty.
     */
    public function execute(GetMarkerQuery $query): Result;
}
