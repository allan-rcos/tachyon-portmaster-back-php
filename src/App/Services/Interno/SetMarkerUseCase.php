<?php

declare(strict_types=1);

namespace App\Services\Interno;

use App\Commands\Marker\SetMarkerCommand;
use App\Services\ISetMarkerUseCase;
use Domain\Models\IMarker;
use Domain\TableModules\IMarkerTM;
use Ds\Map;
use Infra\Repository\IMarkerRepository;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

/**
 * Owns the marker's legal transitions — see {@see ISetMarkerUseCase} for the
 * full table.
 *
 * The read and the write are not atomic against a concurrent caller, and that is
 * survivable rather than ignored: the digest of an unguessable value is only
 * ever known to whoever holds that value, so two callers racing here means the
 * value leaked — at which point one of them loses the race and gets the 409,
 * which is exactly the signal wanted. Making it atomic would need a lock on a
 * `MEMORY` table, serialising every session operation to buy nothing.
 */
final readonly class SetMarkerUseCase implements ISetMarkerUseCase
{
    public function __construct(
        private IMarkerTM $markerTM,
        private IMarkerRepository $markers,
    ) {
    }

    public function execute(SetMarkerCommand $command): Result
    {
        $built = $this->markerTM->create($command->group, $command->value, $command->flag);
        if (!$built->isSuccess()) {
            return Result::failure($built->getErrorId());
        }

        /** @var IMarker $marker */
        $marker = $built->getValue();

        $current = $this->markers->get($marker->group, $marker->key);
        if (!$current->isSuccess()) {
            return Result::failure($current->getErrorId());
        }

        /** @var bool|null $flag */
        $flag = $current->getValue();

        if ($command->flag === true) {
            // Nothing may be raised to live except a value that has no history:
            // an existing marker means either a duplicate issue or a replay.
            if ($flag !== null) {
                return self::conflict(
                    $flag
                        ? 'This value is already marked as active.'
                        : 'This value has already been consumed and cannot be reactivated.',
                    $command->group,
                );
            }

            return $this->markers->set($marker, $command->ttlSeconds);
        }

        // Consuming something that is not live is already true, so it writes
        // nothing — and stays silent about *why* it was not live, which is what
        // keeps "never issued" indistinguishable from "already consumed".
        if ($flag !== true) {
            return Result::void();
        }

        return $this->markers->set($marker, $command->ttlSeconds);
    }

    /**
     * @return Result<never>
     */
    private static function conflict(string $message, string $group): Result
    {
        return Result::failure(Leaf::newError(new LeafContext(
            message: $message,
            details: new Map(['group' => $group]),
            code: 409,
        )));
    }
}
