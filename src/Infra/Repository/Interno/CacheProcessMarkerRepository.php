<?php

/**
 * Cache Process Marker Repository.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra\Repository\Interno;

use Domain\Models\IMarker;
use Ds\Map;
use Infra\Cache\CacheProcessEntryConfig;
use Infra\Cache\ICacheProcessDatabase;
use Infra\Logging\ILogger;
use Infra\Repository\IMarkerGroupRepository;
use Infra\Repository\IMarkerRepository;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

/**
 * {@see IMarkerRepository} over the shared cache.
 *
 * A marker is a boolean filed under a `(group, key)` pair with a lifetime of its
 * own — today, whether a refresh token is still good. It has to be visible to
 * every worker the instant it is written, which is what the shared tables give
 * it and what an in-worker map never could.
 *
 * **This is the repository the per-entry TTL exists for.** {@see set()} is handed
 * a lifetime by whoever writes the marker, because a refresh-token marker has to
 * outlive exactly the token it tracks. It goes straight into a
 * {@see CacheProcessEntryConfig} rather than being reconciled with the
 * database's default, and that default only covers a write that names none.
 *
 * **Expiry is not swept on write.** Reads filter on it and the cache process
 * reclaims on a timer, so nothing here scans — unlike the MEMORY table this
 * replaces, which swept on every write because it already held the table lock.
 *
 * @see IMarkerRepository The contract this implements.
 * @uses ICacheProcessDatabase The `marker` slice, holding one entry per flag.
 * @uses IMarkerGroupRepository Consulted to reject an unregistered group.
 * @see \Infra\Config\CacheLimits::MARKER_TTL_SECONDS The fallback lifetime.
 * @see CacheProcessMarkerGroupRepository What registers the groups.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class CacheProcessMarkerRepository implements IMarkerRepository
{
    /**
     * @var ILogger Channelled copy, so a marker failure is attributable to the
     *              markers rather than to the request carrying them.
     */
    private ILogger $logger;

    /**
     * @param  ICacheProcessDatabase  $database  The `marker` slice of the shared
     *                                           cache.
     * @param  IMarkerGroupRepository  $groups  Consulted to reject a group that
     *                                          was never registered.
     * @param  ILogger  $logger  Rebound to this repository's channel; the
     *                           injected instance is not kept.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private ICacheProcessDatabase $database,
        private IMarkerGroupRepository $groups,
        ILogger $logger,
    ) {
        $this->logger = $logger->withChannel('cache-process-marker-repository');
    }

    /**
     * {@inheritDoc}
     *
     * @param  IMarker  $marker  The group, key and flag to write.
     * @param  int  $ttlSeconds  How long it stays readable. Passed through to the
     *                           cache as this entry's own lifetime, overriding
     *                           the database's default.
     * @return Result<null> Void once written; a 404 when the group was never
     *                       registered, and whatever the store answered when the
     *                       write itself failed — unlike a cached view, a marker
     *                       that did not land is not something a caller may
     *                       shrug off.
     *
     * @copyright 2026 Tachyon
     */
    public function set(IMarker $marker, int $ttlSeconds): Result
    {
        if ($this->groups->getBySlug($marker->group) === null) {
            return $this->unknownGroup($marker->group);
        }

        // Returned, not discarded. A view that failed to cache costs a
        // recomputation; a marker that failed to write is a token that stays
        // valid after being revoked.
        return $this->database->put($marker->flag, new CacheProcessEntryConfig(
            suffix: $this->suffix($marker->group, $marker->key),
            ttlSeconds: $ttlSeconds,
        ));
    }

    /**
     * {@inheritDoc}
     *
     * An expired marker is not a marker: the cache filters it out on read, so
     * this cannot tell one from an absent entry, and neither can the caller —
     * which is the whole point of a marker having a lifetime.
     *
     * @param  string  $group  The registered group to look in.
     * @param  string  $key  The digest the marker is filed under.
     * @return Result<bool> The flag. A 404 when the group was never registered
     *                      and a 404 from the cache when no live marker is filed
     *                      under the key — the caller treats both the same way it
     *                      treats a `false`, but it is the caller that does so.
     *
     * @copyright 2026 Tachyon
     */
    public function get(string $group, string $key): Result
    {
        if ($this->groups->getBySlug($group) === null) {
            return $this->unknownGroup($group);
        }

        $stored = $this->database->get(new CacheProcessEntryConfig($this->suffix($group, $key)));
        if (!$stored->isSuccess()) {
            // Passed through, not flattened. A missing marker is a 404 from the
            // cache and stays one: the caller is what decides that an absent
            // marker and a `false` one mean the same thing.
            return Result::failure($stored->getErrorId());
        }

        $flag = $stored->getValue();

        return is_bool($flag)
            ? Result::success($flag)
            : $this->notABoolean($group, $key, get_debug_type($flag));
    }

    /**
     * Where one marker sits inside the `marker` slice.
     *
     * The group goes first so a future "drop every marker in this group" is a
     * prefix clean, the same way the view cache drops a group.
     *
     * @param  string  $group  The registered group.
     * @param  string  $key  The marker's own key.
     * @return string The suffix identifying the entry.
     *
     * @copyright 2026 Tachyon
     */
    private function suffix(string $group, string $key): string
    {
        return $group.':'.$key;
    }

    /**
     * Reports an entry that is not the boolean a marker is.
     *
     * A 500, because nothing but this repository writes into the `marker` slice,
     * so anything else in there is a bug on this side rather than a caller's
     * mistake.
     *
     * @param  string  $group  The group it was filed under.
     * @param  string  $key  The marker's key.
     * @param  string  $found  What came back instead.
     * @return Result<never> Always a 500 failure.
     *
     * @copyright 2026 Tachyon
     */
    private function notABoolean(string $group, string $key, string $found): Result
    {
        $context = new LeafContext(
            message: 'The stored marker is not a boolean.',
            details: new Map(['group' => $group, 'key' => $key, 'found' => $found]),
            code: 500,
        );
        $this->logger->error($context->message, ($context->details?->toArray() ?? []));

        return Result::failure(Leaf::newError($context));
    }

    /**
     * Reports a group nothing ever registered.
     *
     * A 404 rather than a 422: the caller named something that does not exist,
     * and since groups are declared in code at `WorkerStart`, it means a marker
     * is being written under a name no feature owns.
     *
     * @param  string  $group  What was asked for.
     * @return Result<never> Always a 404 failure.
     *
     * @copyright 2026 Tachyon
     */
    private function unknownGroup(string $group): Result
    {
        $context = new LeafContext(
            message: 'Marker group is not registered.',
            details: new Map(['group' => $group]),
            code: 404,
        );
        $this->logger->error($context->message, ($context->details?->toArray() ?? []));

        return Result::failure(Leaf::newError($context));
    }
}
