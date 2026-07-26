<?php

declare(strict_types=1);

namespace Domain\TableModules\Interno;

use Domain\Models\Internal\Marker;
use Domain\Security\IIndexHasher;
use Domain\TableModules\IMarkerTM;
use Ds\Map;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

/**
 * Builds markers, and is the single point where a flagged value exists in the
 * clear.
 *
 * It takes {@see IIndexHasher} rather than the password hasher for a reason the
 * two interfaces spell out: the digest here has to be *reproducible*, because a
 * lookup recomputes it. A salted hash would produce a different key every time
 * and nothing would ever be found again.
 */
final readonly class MarkerTM implements IMarkerTM
{
    /** Same shape {@see MarkerGroupTM} validates, so an unknown group fails here first. */
    private const string GROUP_PATTERN = '/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/';

    public function __construct(
        private IIndexHasher $hasher,
    ) {
    }

    public function create(string $group, string $plain, bool $flag): Result
    {
        $errors = $this->validate($group, $plain);

        if (!$errors->isEmpty()) {
            return Result::failure(Leaf::newError(new LeafContext(
                message: 'Invalid marker',
                details: $errors,
                code: 422,
            )));
        }

        return Result::success(new Marker(
            group: $group,
            key: $this->hasher->hash($plain),
            flag: $flag,
        ));
    }

    /**
     * @return Map<string, string>
     */
    private function validate(string $group, string $plain): Map
    {
        /** @var Map<string, string> $errors */
        $errors = new Map();

        if ($group === '') {
            $errors->put('group', 'Group is required.');
        } elseif (preg_match(self::GROUP_PATTERN, $group) !== 1) {
            $errors->put('group', 'Group must be a lower-kebab token (e.g. refresh-token).');
        }

        if ($plain === '') {
            // An empty value would hash to a constant, so every caller passing
            // nothing would share one marker — and each would see the others'
            // flag flipping.
            $errors->put('value', 'Value is required.');
        }

        return $errors;
    }
}
