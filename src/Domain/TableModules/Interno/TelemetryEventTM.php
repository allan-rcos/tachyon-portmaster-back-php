<?php

declare(strict_types=1);

namespace Domain\TableModules\Interno;

use Domain\Models\Internal\TelemetryEvent;
use Domain\TableModules\ITelemetryEventTM;
use Ds\Map;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

final readonly class TelemetryEventTM implements ITelemetryEventTM
{
    /**
     * A single lower-kebab token (`load`, `unload`, `seal`). Unlike a permission
     * there is no `domain:` half — the container is always the subject.
     */
    private const string SLUG_PATTERN = '/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/';

    /** Matches the `slug` column of the `telemetry_events` registry table. */
    private const int MAX_SLUG_LENGTH = 64;

    public function create(string $slug): Result
    {
        $errors = $this->validate($slug);

        if (!$errors->isEmpty()) {
            return Result::failure(Leaf::newError(new LeafContext(
                message: 'Invalid telemetry event metadata',
                details: $errors,
                code: 422,
            )));
        }

        return Result::success(new TelemetryEvent(slug: $slug));
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
            $errors->put('slug', 'Slug must be a lower-kebab token (e.g. load, unload).');
        } elseif (mb_strlen($slug) > self::MAX_SLUG_LENGTH) {
            $errors->put('slug', 'Slug must not exceed '.self::MAX_SLUG_LENGTH.' characters.');
        }

        return $errors;
    }
}
