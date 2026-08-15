<?php

/**
 * Permission Table Module.
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

use Domain\Models\Internal\Permission;
use Domain\Models\IPermission;
use Domain\TableModules\IPermissionTM;
use Ds\Map;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

/**
 * Builds validated permissions, and refuses to build malformed ones.
 *
 * Takes no id generator, unlike the other table modules: a permission is
 * identified by its slug, and its numeric id is a registry index assigned on
 * insertion rather than anything the domain mints.
 *
 * @see IPermissionTM The contract.
 * @see IPermission Why a permission carries nothing but its identity.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class PermissionTM implements IPermissionTM
{
    /**
     * `domain:action`, both halves lower-kebab. Enforcing the shape here is what
     * keeps the slug usable as the wire and persistence identifier: the
     * application layer invents the values freely, but never in a form that
     * would break a client parsing them.
     *
     * @var string PCRE anchored at both ends.
     */
    private const string SLUG_PATTERN = '/^[a-z][a-z0-9]*(-[a-z0-9]+)*:[a-z][a-z0-9]*(-[a-z0-9]+)*$/';

    /**
     * Bounds the slug a use case may declare. There is no column behind it any
     * more — the catalogue lives in the cache process — so this is the only thing
     * enforcing it, and it is enforced at boot, where an over-long slug is a
     * failure to declare rather than a truncation nobody notices.
     *
     * @var int Characters.
     */
    private const int MAX_SLUG_LENGTH = 64;

    /**
     * Builds a permission after validating its slug.
     *
     * The result carries `id = 0` — the registry assigns the real index when it
     * stores the row.
     *
     * @param  string  $slug  `domain:action`, lower-kebab on both sides.
     * @return Result<IPermission> A 422 failure when the slug is malformed.
     *
     * @copyright 2026 Tachyon
     */
    public function create(string $slug): Result
    {
        $errors = $this->validate($slug);

        if (!$errors->isEmpty()) {
            return Result::failure(Leaf::newError(new LeafContext(
                message: 'Invalid permission metadata',
                details: $errors,
                code: 422,
            )));
        }

        return Result::success(new Permission(slug: $slug));
    }

    /**
     * Every rule a slug must satisfy.
     *
     * The checks are a chain rather than independent: an empty slug would also
     * fail the pattern, and reporting both would say the same thing twice.
     *
     * Length is measured with `mb_strlen()` while the pattern already restricts
     * the slug to ASCII — belt and braces, since the column limit is what this
     * is protecting and a future pattern change should not silently lift it.
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
            $errors->put('slug', 'Slug must follow "domain:action" in lower-kebab (e.g. product:create).');
        } elseif (mb_strlen($slug) > self::MAX_SLUG_LENGTH) {
            $errors->put('slug', 'Slug must not exceed '.self::MAX_SLUG_LENGTH.' characters.');
        }

        return $errors;
    }
}
