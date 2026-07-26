<?php

declare(strict_types=1);

namespace Infra\Repository\Interno;

use Atlas\Query\Delete;
use Atlas\Query\Insert;
use Atlas\Query\Select;
use Atlas\Query\Update;
use Domain\Models\IMarker;
use Ds\Map;
use Infra\Database\IPdoTransaction;
use Infra\Logging\ILogger;
use Infra\Repository\IMarkerGroupRepository;
use Infra\Repository\IMarkerRepository;
use PDO;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;
use Throwable;

/**
 * {@see IMarkerRepository} over the `markers` table.
 *
 * The table is `ENGINE=MEMORY`, which shapes both operations:
 *
 *  - reads filter `expires_at > NOW()` instead of deleting what they find
 *    expired, so a read never takes the table-level write lock;
 *  - {@see set()} sweeps expired rows before writing, because it is already
 *    taking that lock — and a MariaDB event does the same hourly, for the case
 *    where nothing is written at all.
 *
 * Non-transactional storage, despite joining the caller's transaction: a
 * `ROLLBACK` will not undo a marker write. That is acceptable here and nowhere
 * else — the worst case is a token marked consumed by a request that then
 * failed, which logs someone out early rather than letting a consumed token
 * live on.
 */
final readonly class SqlMarkerRepository implements IMarkerRepository
{
    private const string TABLE = 'markers';

    private ILogger $logger;

    public function __construct(
        ILogger $logger,
        private IPdoTransaction $session,
        private IMarkerGroupRepository $groups,
    ) {
        $this->logger = $logger->withChannel('sql-marker-repository');
    }

    public function set(IMarker $marker, int $ttlSeconds): Result
    {
        $group = $this->groups->getBySlug($marker->group);
        if ($group === null) {
            return $this->unknownGroup($marker->group);
        }

        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) {
            return Result::failure($result->getErrorId());
        }

        /** @var PDO $pdo */
        $pdo = $result->getValue();

        $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);

        try {
            // Sweep first: the lock is already ours for the write below, so the
            // reclaim is free, and it keeps the table from growing without bound
            // between the hourly event's runs.
            Delete::new($pdo)
                ->from(self::TABLE)
                ->where('expires_at <= NOW()')
                ->perform();

            $updated = Update::new($pdo)
                ->table(self::TABLE)
                ->columns([
                    'flag' => $marker->flag ? 1 : 0,
                    'expires_at' => $expiresAt,
                ])
                ->where('group_id = ', $group->id)
                ->where('hash_key = ', $marker->key)
                ->perform();

            if ($updated->rowCount() === 0) {
                Insert::new($pdo)
                    ->into(self::TABLE)
                    ->columns([
                        'group_id' => $group->id,
                        'hash_key' => $marker->key,
                        'flag' => $marker->flag ? 1 : 0,
                        'expires_at' => $expiresAt,
                    ])
                    ->perform();
            }
        } catch (Throwable $e) {
            return $this->fail('write the marker', $marker->group, $e);
        }

        return Result::void();
    }

    public function get(string $group, string $key): Result
    {
        $registered = $this->groups->getBySlug($group);
        if ($registered === null) {
            return $this->unknownGroup($group);
        }

        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) {
            return Result::failure($result->getErrorId());
        }

        /** @var PDO $pdo */
        $pdo = $result->getValue();

        try {
            $row = Select::new($pdo)
                ->columns('flag')
                ->from(self::TABLE)
                ->where('group_id = ', $registered->id)
                ->where('hash_key = ', $key)
                // An expired marker is not a marker. Filtering here (rather than
                // deleting) is what keeps reads off the write lock.
                ->where('expires_at > NOW()')
                ->fetchOne();
        } catch (Throwable $e) {
            return $this->fail('read the marker', $group, $e);
        }

        if (!is_array($row)) {
            return Result::success(null);
        }

        return Result::success((bool) ($row['flag'] ?? false));
    }

    /**
     * @return Result<never>
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

    /**
     * @return Result<never>
     */
    private function fail(string $action, string $group, Throwable $e): Result
    {
        $context = new LeafContext(
            message: 'An error occurred while trying to '.$action,
            details: new Map(['group' => $group, 'error' => $e->getMessage()]),
            code: 500,
        );
        $this->logger->error($context->message, ($context->details?->toArray() ?? []));

        return Result::failure(Leaf::newError($context));
    }
}
