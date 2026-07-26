<?php

declare(strict_types=1);

namespace Domain\TableModules\Interno;

use Domain\Models\Internal\Permission;
use Domain\TableModules\IPermissionTM;
use Ds\Map;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

final readonly class PermissionTM implements IPermissionTM
{
    /**
     * `domain:action`, both halves lower-kebab. Enforcing the shape here is what
     * keeps the slug usable as the wire/persistence identifier: the application
     * layer invents the values freely, but never in a form that would break a
     * client parsing them.
     */
    private const string SLUG_PATTERN = '/^[a-z][a-z0-9]*(-[a-z0-9]+)*:[a-z][a-z0-9]*(-[a-z0-9]+)*$/';

    /**
     * Matches the `slug` column of the `permissions` registry table. Enforced
     * here because that table is `ENGINE=MEMORY`, where an over-long value is a
     * write error at boot rather than a truncation nobody notices.
     */
    private const int MAX_SLUG_LENGTH = 64;

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
     * @return Map<string, string>
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
