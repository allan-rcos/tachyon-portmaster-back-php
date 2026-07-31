<?php

/**
 * SQL Manifest Repository.
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

use Atlas\Query\Delete;
use Atlas\Query\Insert;
use Atlas\Query\Select;
use Domain\Enums\TelemetryEvent;
use Domain\ID\Base62;
use Domain\Models\IManifestCargo;
use Infra\Database\IPdoTransaction;
use Infra\Entity\ManifestCargoEntity;
use Infra\Repository\IManifestRepository;
use Ds\Map;
use PDO;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;
use Infra\Logging\ILogger;
use Throwable;

/**
 * {@see IManifestRepository} over the `container_items` and `telemetry_logs`
 * tables.
 *
 * Every method opens by asking {@see IPdoTransaction} for the caller's
 * connection, so the writes land inside whatever boundary the use case opened
 * and a rollback there undoes them.
 *
 * All five failure paths funnel through the private {@see fail()}, which is what
 * keeps the 500s uniform across two tables and five very differently shaped
 * statements.
 *
 * @see IManifestRepository The contract this implements.
 * @uses ILogger Records failures under the `sql-manifest-repository` channel.
 * @uses IPdoTransaction Supplies the caller's connection.
 * @uses ManifestCargoEntity Maps between the domain model and the cargo row.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class SqlManifestRepository implements IManifestRepository
{
    /**
     * @var string The cargo lines: one row per container-and-product pair.
     */
    private const string CARGO_TABLE = 'container_items';

    /**
     * @var string The append-only history beside them.
     */
    private const string TELEMETRY_TABLE = 'telemetry_logs';

    /**
     * @var ILogger Channelled copy, so these lines are attributable to this
     *              repository rather than to the request at large.
     */
    private ILogger $logger;

    /**
     * @param  ILogger  $logger  Rebound to this repository's channel; the
     *                           injected instance is not kept.
     * @param  IPdoTransaction  $session  The caller's transaction, asked for its
     *                                    connection on every call rather than
     *                                    held, since it changes per request.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        ILogger $logger,
        private IPdoTransaction $session,
    ) {
        $this->logger = $logger->withChannel('sql-manifest-repository');
    }

    /**
     * Loads one cargo line, matched on both halves of its composite key.
     *
     * @param  string  $containerId  Base62 id of the container.
     * @param  string  $productId  Base62 id of the product.
     * @return Result<IManifestCargo|null> The line, or a successful null when
     *                                     the select returned nothing; a 500
     *                                     failure when it threw.
     *
     * @copyright 2026 Tachyon
     */
    public function findCargo(string $containerId, string $productId): Result
    {
        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) return Result::failure($result->getErrorId());
        /** @var PDO $pdo */
        $pdo = $result->getValue();

        try {
            $row = Select::new($pdo)
                ->columns('*')
                ->from(self::CARGO_TABLE)
                ->where('container_id = :container_id')
                ->where('product_id = :product_id')
                ->bindValue('container_id', Base62::decode($containerId))
                ->bindValue('product_id', Base62::decode($productId))
                ->fetchOne();
        } catch (Throwable $e) {
            return $this->fail('load the cargo item', ['containerId' => $containerId, 'productId' => $productId], $e);
        }

        if (!$row) {
            return Result::success(null);
        }

        /** @var array<string, mixed> $row */
        return Result::success(ManifestCargoEntity::unserialize($row));
    }

    /**
     * Writes the cargo line as a delete followed by an insert.
     *
     * Not an `ON DUPLICATE KEY UPDATE`: deleting first means the row that ends
     * up stored is exactly what was serialised, with no column of a previous
     * line surviving. Both statements share the caller's transaction, so the
     * gap between them is never observable.
     *
     * The ids are taken from the serialised row rather than decoded again, since
     * {@see ManifestCargoEntity} has already converted them.
     *
     * @param  IManifestCargo  $cargo  Carries both ids and the resulting
     *                                 quantity and weight, already validated.
     * @return Result<null> Void on success; a 500 failure when either statement
     *                      threw, which includes a container or product the
     *                      foreign keys no longer recognise.
     *
     * @copyright 2026 Tachyon
     */
    public function upsertCargo(IManifestCargo $cargo): Result
    {
        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) return Result::failure($result->getErrorId());
        /** @var PDO $pdo */
        $pdo = $result->getValue();

        $data = ManifestCargoEntity::map($cargo)->serialize();

        try {
            Delete::new($pdo)
                ->from(self::CARGO_TABLE)
                ->where('container_id = :container_id')
                ->where('product_id = :product_id')
                ->bindValue('container_id', $data['container_id'])
                ->bindValue('product_id', $data['product_id'])
                ->perform();

            Insert::new($pdo)
                ->into(self::CARGO_TABLE)
                ->columns($data)
                ->perform();
        } catch (Throwable $e) {
            return $this->fail('upsert the cargo item', ['containerId' => $cargo->containerId, 'productId' => $cargo->productId], $e);
        }

        return Result::void();
    }

    /**
     * Removes one cargo line, matched on both halves of its composite key.
     *
     * @param  string  $containerId  Base62 id of the container.
     * @param  string  $productId  Base62 id of the product.
     * @return Result<null> Void on success; a 500 failure when the delete threw.
     *                      Matching no line is *not* a failure.
     *
     * @copyright 2026 Tachyon
     */
    public function deleteCargo(string $containerId, string $productId): Result
    {
        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) return Result::failure($result->getErrorId());
        /** @var PDO $pdo */
        $pdo = $result->getValue();

        try {
            Delete::new($pdo)
                ->from(self::CARGO_TABLE)
                ->where('container_id = :container_id')
                ->where('product_id = :product_id')
                ->bindValue('container_id', Base62::decode($containerId))
                ->bindValue('product_id', Base62::decode($productId))
                ->perform();
        } catch (Throwable $e) {
            return $this->fail('delete the cargo item', ['containerId' => $containerId, 'productId' => $productId], $e);
        }

        return Result::void();
    }

    /**
     * Deletes every cargo line of a container in one statement.
     *
     * The telemetry table is untouched.
     *
     * @param  string  $containerId  Base62 id of the container.
     * @return Result<null> Void on success; a 500 failure when the delete threw.
     *                      An already-empty manifest is *not* a failure.
     *
     * @copyright 2026 Tachyon
     */
    public function clearManifest(string $containerId): Result
    {
        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) return Result::failure($result->getErrorId());
        /** @var PDO $pdo */
        $pdo = $result->getValue();

        try {
            Delete::new($pdo)
                ->from(self::CARGO_TABLE)
                ->where('container_id = :container_id')
                ->bindValue('container_id', Base62::decode($containerId))
                ->perform();
        } catch (Throwable $e) {
            return $this->fail('clear the manifest', ['containerId' => $containerId], $e);
        }

        return Result::void();
    }

    /**
     * Appends a telemetry row.
     *
     * The timestamp is set to the database's `NOW()` rather than to a value PHP
     * computed, so entries written by different workers order against one clock.
     * The id is the table's auto-increment, so neither is bound as a parameter.
     *
     * The event is stored as its own string value, not as an ordinal: the column
     * outlives any particular build, and a row read back years later has to say
     * what happened without the enum's declaration order being at hand.
     *
     * @param  string  $containerId  Base62 id of the container.
     * @param  TelemetryEvent  $event  What the entry records having happened.
     * @param  string|null  $description  Free text, or null.
     * @return Result<null> Void on success; a 500 failure when the insert threw.
     *
     * @copyright 2026 Tachyon
     */
    public function insertTelemetry(string $containerId, TelemetryEvent $event, ?string $description): Result
    {
        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) return Result::failure($result->getErrorId());
        /** @var PDO $pdo */
        $pdo = $result->getValue();

        try {
            Insert::new($pdo)
                ->into(self::TELEMETRY_TABLE)
                ->columns([
                    'container_id' => Base62::decode($containerId),
                    'event' => $event->value,
                    'description' => $description,
                ])
                ->set('timestamp', 'NOW()')
                ->perform();
        } catch (Throwable $e) {
            return $this->fail('record the telemetry event', ['containerId' => $containerId], $e);
        }

        return Result::void();
    }

    /**
     * Logs a failed statement and turns it into the 500 every method here
     * returns.
     *
     * The caller's details are stringified and the exception message appended,
     * so the log line carries which ids were in play alongside what the driver
     * said.
     *
     * @param  string  $action  What was being attempted, phrased to follow
     *                          "An error occurred while trying to".
     * @param  array<string, scalar>  $details  Identifying values for the log
     *                                          line, keyed by name.
     * @param  Throwable  $e  What the statement threw.
     * @return Result<null> Always a 500 failure; the return type exists so
     *                      callers can hand it straight back.
     *
     * @copyright 2026 Tachyon
     */
    private function fail(string $action, array $details, Throwable $e): Result
    {
        $context = new LeafContext(
            message: "An error occurred while trying to $action",
            details: new Map(array_map(
                static fn (mixed $value): string => (string) $value,
                [...$details, 'error' => $e->getMessage()],
            )),
            code: 500,
        );
        $this->logger->error($context->message, ($context->details?->toArray() ?? []));

        return Result::failure(Leaf::newError($context));
    }
}
