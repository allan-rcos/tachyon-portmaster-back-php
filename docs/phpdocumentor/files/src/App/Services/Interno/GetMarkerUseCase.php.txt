<?php

/**
 * Get Marker Use Case.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

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
 *
 * @see IGetMarkerUseCase The contract this implements.
 * @see SetMarkerUseCase The write side, and the transition rules.
 * @uses IMarkerTM Produces the digest, the same way the write did.
 * @uses IMarkerRepository Reads the flag.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class GetMarkerUseCase implements IGetMarkerUseCase
{
    /**
     * Takes no registrar and no unit of work — nothing to authorize, nothing to
     * write.
     *
     * @param  IMarkerTM  $markerTM  Hashes the value into the stored digest.
     * @param  IMarkerRepository  $markers  Read from.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IMarkerTM $markerTM,
        private IMarkerRepository $markers,
    ) {
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
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
