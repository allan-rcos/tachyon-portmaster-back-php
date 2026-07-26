<?php

declare(strict_types=1);

namespace App\Queries\Marker;

/**
 * Reads the flag currently held for a value.
 *
 * Carries no {@see \App\Context\UserContext} for the same reason
 * {@see \App\Commands\Marker\SetMarkerCommand} does not: knowing the value *is*
 * the authorization, and the callers that read markers are the ones deciding
 * whether a caller is authenticated at all.
 */
final readonly class GetMarkerQuery
{
    public function __construct(
        public string $group,
        public string $value,
    ) {
    }
}
