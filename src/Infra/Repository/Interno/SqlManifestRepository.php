<?php

declare(strict_types=1);

namespace Infra\Repository\Interno;

use Atlas\Query\Delete;
use Atlas\Query\Insert;
use Atlas\Query\Select;
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

final readonly class SqlManifestRepository implements IManifestRepository
{
    private const string CARGO_TABLE = 'container_items';
    private const string TELEMETRY_TABLE = 'telemetry_logs';
    private ILogger $logger;

    public function __construct(
        ILogger $logger,
        private IPdoTransaction $session,
    ) {
        $this->logger = $logger->withChannel('sql-manifest-repository');
    }

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

    public function insertTelemetry(string $containerId, string $event, ?string $description): Result
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
                    'event' => $event,
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
     * @param  array<string, scalar>  $details
     * @return Result<null>
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
