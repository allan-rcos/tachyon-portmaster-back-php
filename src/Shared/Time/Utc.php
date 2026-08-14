<?php

/**
 * UTC Instant Helper.
 *
 * @category Shared
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Shared\Time;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Renders a stored datetime as the ISO-8601 UTC instant that leaves the API.
 *
 * Every datetime in this system is UTC, which the clock, the connection and the
 * server all enforce. What that does not do by itself is *say so* to a client:
 * MariaDB renders a `DATETIME` as `2026-08-13 14:32:05`, naming no zone at all,
 * so a caller has to be told out of band which one it is — and being told out of
 * band is how a datetime gets misread. This is the one place that adds the `Z`.
 *
 * It lives in `Shared` rather than beside the single query that needs it today
 * because the rule is the system's, not that query's.
 *
 * Nothing converts *into* UTC here, deliberately: no request carries a datetime,
 * so the only direction that exists is out. A field that arrives from a client
 * one day is a parser to add here, not a zone to guess at.
 *
 * @see docs/database.md Where the rule and its four enforcement points are set out.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class Utc
{
    /**
     * @var string What MariaDB hands back for a `DATETIME` column.
     */
    private const string STORED_FORMAT = 'Y-m-d H:i:s';

    /**
     * @var string ISO-8601, with the literal `Z` that names the zone.
     */
    private const string WIRE_FORMAT = 'Y-m-d\TH:i:s\Z';

    /**
     * Turns a stored `DATETIME` into its ISO-8601 UTC form.
     *
     * The input is read as UTC rather than as the process's zone, which is true
     * by construction: the connection that produced it was on UTC. Reading it as
     * "local" would be right only for as long as the process happened to agree.
     *
     * Anything unparseable comes back null rather than raising. A malformed
     * timestamp is a row that should not exist, and refusing to render a whole
     * container summary over one bad telemetry line would turn a cosmetic defect
     * into a failed read.
     *
     * @param  string|null  $stored  As the driver returned it; null passes
     *                               through, since the column is nullable at the
     *                               view level.
     * @return string|null The instant with its `Z`, or null when there was
     *                     nothing to render or it did not parse.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function iso8601(?string $stored): ?string
    {
        $stored = $stored === null ? '' : trim($stored);
        if ($stored === '') {
            return null;
        }

        try {
            $parsed = DateTimeImmutable::createFromFormat(
                self::STORED_FORMAT,
                $stored,
                new DateTimeZone('UTC'),
            );
        } catch (Throwable) {
            return null;
        }

        if ($parsed === false) {
            return null;
        }

        // createFromFormat is lenient: it does not reject a field out of range,
        // it rolls it over, so "2026-13-45 99:99:99" parses into a perfectly
        // valid instant some months away rather than answering false. Formatting
        // back and comparing is what catches that — a value the database wrote
        // reproduces itself exactly, and anything that does not was never a
        // stored datetime.
        if ($parsed->format(self::STORED_FORMAT) !== $stored) {
            return null;
        }

        return $parsed->format(self::WIRE_FORMAT);
    }
}
