<?php

/**
 * Marker Table Module.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Domain\TableModules\Interno;

use Domain\Models\IMarker;
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
 *
 * @see IMarkerTM The contract.
 * @see IIndexHasher Why this flavour and not the secure one.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class MarkerTM implements IMarkerTM
{
    /**
     * @var string Same shape {@see MarkerGroupTM} validates, so an unknown group
     *             fails here first rather than at the repository.
     */
    private const string GROUP_PATTERN = '/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/';

    /**
     * @param  IIndexHasher  $hasher  Produces the reproducible digest a lookup
     *                                recomputes.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IIndexHasher $hasher,
    ) {
    }

    /**
     * Hashes the value and builds the marker around the digest.
     *
     * @param  string  $group  Slug of a registered marker group.
     * @param  string  $plain  The value to flag; never retained.
     * @param  bool  $flag  `true` to mark live, `false` to consume.
     * @return Result<IMarker> A 422 failure when the group slug is malformed or
     *                         the value is empty.
     *
     * @copyright 2026 Tachyon
     */
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
     * Every rule a marker must satisfy before it is hashed.
     *
     * @param  string  $group  Group slug.
     * @param  string  $plain  The value to flag.
     * @return Map<string, string> Field name to message; empty when valid.
     *
     * @copyright 2026 Tachyon
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
