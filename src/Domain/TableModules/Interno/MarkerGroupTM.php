<?php

/**
 * Marker Group Table Module.
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

use Domain\Models\IMarkerGroup;
use Domain\Models\Internal\MarkerGroup;
use Domain\TableModules\IMarkerGroupTM;
use Ds\Map;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

/**
 * Builds validated marker groups, and refuses to build malformed ones.
 *
 * Mirrors {@see PermissionTM} — same metadata family, same shape — differing
 * only in the slug pattern.
 *
 * @see IMarkerGroupTM The contract.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class MarkerGroupTM implements IMarkerGroupTM
{
    /**
     * A single lower-kebab token (`refresh-token`). Unlike a permission there is
     * no `domain:action` half: a group names a kind of flag, not an operation.
     *
     * @var string PCRE anchored at both ends.
     */
    private const string SLUG_PATTERN = '/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/';

    /**
     * @var int Matches the `slug` column of the `marker_groups` registry table.
     */
    private const int MAX_SLUG_LENGTH = 64;

    /**
     * Builds a marker group after validating its slug.
     *
     * The result carries `id = 0` — the registry assigns the real index when it
     * stores the row.
     *
     * @param  string  $slug  Lower-kebab single token.
     * @return Result<IMarkerGroup> A 422 failure when the slug is malformed.
     *
     * @copyright 2026 Tachyon
     */
    public function create(string $slug): Result
    {
        $errors = $this->validate($slug);

        if (!$errors->isEmpty()) {
            return Result::failure(Leaf::newError(new LeafContext(
                message: 'Invalid marker group metadata',
                details: $errors,
                code: 422,
            )));
        }

        return Result::success(new MarkerGroup(slug: $slug));
    }

    /**
     * Every rule a group slug must satisfy.
     *
     * @param  string  $slug  The candidate slug.
     * @return Map<string, string> Field name to message; empty when valid.
     *
     * @copyright 2026 Tachyon
     */
    private function validate(string $slug): Map
    {
        /** @var Map<string, string> $errors */
        $errors = new Map();

        if ($slug === '') {
            $errors->put('slug', 'Slug is required.');
        } elseif (preg_match(self::SLUG_PATTERN, $slug) !== 1) {
            $errors->put('slug', 'Slug must be a lower-kebab token (e.g. refresh-token).');
        } elseif (mb_strlen($slug) > self::MAX_SLUG_LENGTH) {
            $errors->put('slug', 'Slug must not exceed '.self::MAX_SLUG_LENGTH.' characters.');
        }

        return $errors;
    }
}
