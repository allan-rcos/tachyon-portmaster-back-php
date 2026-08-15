<?php

/**
 * Cache Process Metadata Registry.
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

use Ds\Seq;
use Infra\Cache\ICacheProcessDatabase;
use Infra\Logging\ILogger;
use Shared\Exceptions\Result;

/**
 * Shared base for the registries filled from code at `WorkerStart`.
 *
 * Permissions and marker groups are the same thing twice: a family of entries
 * that exist only because some constructor declared them, keyed by slug and
 * numbered by declaration order. Subclasses supply {@see hydrate()} and
 * {@see label()} and nothing else.
 *
 * **The whole catalogue is one entry.** {@see ICacheProcessDatabase} has three
 * methods and no listing, and `all()` needs one — so the registry keeps its
 * entire contents under the database's bare key and rewrites it on every
 * addition. That is affordable precisely because these are catalogues: they are
 * bounded by how much code is written, they are filled once at boot, and they
 * are never written again while the process serves traffic.
 *
 * **Why the ids agree across workers.** The catalogue is a pure function of the
 * source: every worker runs the same constructors in the same order and so
 * derives the same slugs with the same indices. The read-modify-write below is
 * not atomic and two workers registering at once can briefly overwrite each
 * other, but neither can invent a different answer — each keeps appending until
 * its own full list is stored, and every worker's full list is identical. It is
 * the same "the copies agree by construction" ADR 0002 relied on, with one copy
 * instead of four. The window is microseconds wide and closes before any worker
 * finishes `onWorkerStart`, which is before it serves anything.
 *
 * The database this is given carries {@see \Infra\Config\CacheLimits::TTL_FOREVER}:
 * a worker that forgot what it was allowed to do would start failing
 * authorization, so these entries are the ones the sweeper never touches.
 *
 * @see \Infra\Repository\IPermissionRepository One of the two contracts served.
 * @see CacheProcessPermissionRepository A subclass.
 * @see CacheProcessMarkerGroupRepository The other.
 * @see docs/adr/0011-cache-em-processo-openswoole.md Why the registries left the database.
 *
 * @template TModel of object The model this registry hydrates — a permission or
 *                            a marker group. Subclasses bind it through
 *                            `@extends`, which is what lets their `getBySlug()`
 *                            and `all()` narrow without a cast.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
abstract class CacheProcessMetadataRegistry
{
    /**
     * @var ILogger Channelled copy, so a registration is attributable to the
     *              registry rather than to whatever was booting around it.
     */
    protected readonly ILogger $logger;

    /**
     * @param  ICacheProcessDatabase  $database  Holds the whole catalogue under
     *                                           its bare key.
     * @param  ILogger  $logger  Rebound to `<label>-registry`; the injected
     *                           instance is not kept.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private readonly ICacheProcessDatabase $database,
        ILogger $logger,
    ) {
        $this->logger = $logger->withChannel($this->label().'-registry');
    }

    /**
     * Builds one entry of this registry's own type.
     *
     * @param  string  $slug  The entry's identity.
     * @param  int  $id  Its index, one-based, in declaration order.
     * @return TModel The model the subclass's contract answers.
     *
     * @copyright 2026 Tachyon
     */
    abstract protected function hydrate(string $slug, int $id): object;

    /**
     * What this registry holds, singular and lower case, for log lines.
     *
     * @return string For example `permission`.
     *
     * @copyright 2026 Tachyon
     */
    abstract protected function label(): string;

    /**
     * Records the slug if it is not already known, and answers the entry either
     * way.
     *
     * Idempotent by slug, and deliberately read-then-append rather than an
     * upsert: a slug *is* the whole entry, so one that already exists is already
     * correct and there is nothing an upsert could update. Every worker calls
     * this for every slug it declares, so the common case after the first worker
     * is the early return.
     *
     * @param  string  $slug  What to register.
     * @return Result<TModel> The registered entry, with its id. A failure only
     *                        when the catalogue could not be written back, which
     *                        means a worker that does not know its own metadata —
     *                        worth failing the boot over.
     *
     * @copyright 2026 Tachyon
     */
    protected function register(string $slug): Result
    {
        $catalogue = $this->catalogue();

        $existing = $catalogue[$slug] ?? null;
        if ($existing !== null) {
            return Result::success($this->hydrate($slug, $existing));
        }

        $id = count($catalogue) + 1;
        $catalogue[$slug] = $id;

        $stored = $this->database->put($catalogue);
        if (!$stored->isSuccess()) {
            $this->logger->error('An error occurred while trying to register the '.$this->label(), [
                'slug' => $slug,
            ]);

            return Result::failure($stored->getErrorId());
        }

        return Result::success($this->hydrate($slug, $id));
    }

    /**
     * The entry filed under this slug, if any.
     *
     * @param  string  $slug  What to look for.
     * @return TModel|null The entry, or `null` when it was never registered.
     *
     * @copyright 2026 Tachyon
     */
    protected function find(string $slug): ?object
    {
        $id = $this->catalogue()[$slug] ?? null;

        return $id === null ? null : $this->hydrate($slug, $id);
    }

    /**
     * The entry carrying this id, if any.
     *
     * A linear scan, which is what the shape allows and what the size makes
     * irrelevant: a catalogue is bounded by how much code is written.
     *
     * @param  int  $id  The index to look for.
     * @return TModel|null The entry, or `null` when nothing carries that id.
     *
     * @copyright 2026 Tachyon
     */
    protected function findById(int $id): ?object
    {
        foreach ($this->catalogue() as $slug => $registered) {
            if ($registered === $id) {
                return $this->hydrate($slug, $id);
            }
        }

        return null;
    }

    /**
     * Everything registered, in declaration order.
     *
     * @return Seq<TModel> The whole catalogue; empty when nothing has registered
     *                     yet, which outside of boot cannot happen.
     *
     * @copyright 2026 Tachyon
     */
    protected function listAll(): Seq
    {
        /** @var Seq<TModel> $items */
        $items = new Seq();

        foreach ($this->catalogue() as $slug => $id) {
            $items->push($this->hydrate($slug, $id));
        }

        return $items;
    }

    /**
     * Which of these slugs the catalogue does not hold.
     *
     * Reads the catalogue once, whatever the size of the batch — which is the
     * reason the public surface takes a list rather than one slug at a time.
     *
     * @param  list<string>  $slugs  Candidates.
     * @return list<string> Those absent, in the order given and deduplicated.
     *
     * @copyright 2026 Tachyon
     */
    protected function missing(array $slugs): array
    {
        $catalogue = $this->catalogue();

        /** @var list<string> $absent */
        $absent = [];
        foreach ($slugs as $slug) {
            if (!isset($catalogue[$slug]) && !in_array($slug, $absent, true)) {
                $absent[] = $slug;
            }
        }

        return $absent;
    }

    /**
     * The stored catalogue, as a slug-to-id map.
     *
     * **This is where the cache's 404 is answered rather than passed on**, and it
     * is the one place in this layer where that is right: before the first
     * registration the catalogue genuinely is empty, so "nothing is filed under
     * this key" and "the catalogue is empty" are the same statement. Returning a
     * failure would make every caller re-derive that.
     *
     * A 500 is treated the same way and deliberately so — a catalogue this build
     * cannot decode is one written by an older deploy, and rebuilding it from the
     * declarations is exactly what {@see register()} is about to do.
     *
     * @return array<string, int> Slug to one-based index, in insertion order.
     *
     * @copyright 2026 Tachyon
     */
    private function catalogue(): array
    {
        $stored = $this->database->get();
        if (!$stored->isSuccess()) {
            return [];
        }

        $stored = $stored->getValue();
        if (!is_array($stored)) {
            return [];
        }

        /** @var array<string, int> $catalogue */
        $catalogue = [];
        foreach ($stored as $slug => $id) {
            if (is_string($slug) && is_int($id)) {
                $catalogue[$slug] = $id;
            }
        }

        return $catalogue;
    }
}
