<?php

declare(strict_types=1);

namespace App\Services\Interno;

use App\Queries\Marker\GetMarkerQuery;
use App\Services\IGetMarkerUseCase;
use Domain\Models\IMarker;
use Domain\TableModules\IMarkerTM;
use Infra\Repository\IMarkerRepository;
use Shared\Exceptions\Result;

/**
 * Reads a marker's flag.
 *
 * It goes through the table module rather than hashing here, even though it only
 * needs a key: the digest must be produced the same way on the read and the
 * write, and the only way to guarantee that is to have one place that produces
 * it. The `flag` handed to `create()` is irrelevant to the lookup — the key is
 * all this uses.
 */
final readonly class GetMarkerUseCase implements IGetMarkerUseCase
{
    public function __construct(
        private IMarkerTM $markerTM,
        private IMarkerRepository $markers,
    ) {
    }

    public function execute(GetMarkerQuery $query): Result
    {
        $built = $this->markerTM->create($query->group, $query->value, false);
        if (!$built->isSuccess()) {
            return Result::failure($built->getErrorId());
        }

        /** @var IMarker $marker */
        $marker = $built->getValue();

        return $this->markers->get($marker->group, $marker->key);
    }
}
