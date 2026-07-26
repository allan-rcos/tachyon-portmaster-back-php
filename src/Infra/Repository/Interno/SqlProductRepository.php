<?php

declare(strict_types=1);

namespace Infra\Repository\Interno;

use Atlas\Query\Delete;
use Atlas\Query\Insert;
use Atlas\Query\Select;
use Atlas\Query\Update;
use Domain\ID\Base62;
use Domain\Models\IProduct;
use Infra\Database\IPdoTransaction;
use Infra\Entity\ProductEntity;
use Infra\Repository\IProductRepository;
use Ds\Map;
use PDO;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;
use Infra\Logging\ILogger;
use Throwable;

final readonly class SqlProductRepository implements IProductRepository
{
    private const string TABLE_NAME = 'products';
    private ILogger $logger;

    public function __construct(
        ILogger $logger,
        private IPdoTransaction $session,
    ) {
        $this->logger = $logger->withChannel('sql-product-repository');
    }

    public function findById(string $id): Result
    {
        $this->logger->debug("Finding product with id $id");
        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) return Result::failure($result->getErrorId());
        /** @var PDO $pdo */
        $pdo = $result->getValue();

        try {
            $row = Select::new($pdo)
                ->columns('*')
                ->from(self::TABLE_NAME)
                ->where('id = :id')
                ->bindValue('id', Base62::decode($id))
                ->fetchOne();
        } catch (Throwable $e) {
            $context = new LeafContext(
                message: 'An error occurred while trying to find the product',
                details: new Map(['productId' => $id, 'error' => $e->getMessage()]),
                code: 500,
            );
            $this->logger->error($context->message, ($context->details?->toArray() ?? []));
            return Result::failure(Leaf::newError($context));
        }

        if (!$row) {
            return Result::failure(Leaf::newError(new LeafContext(
                message: "Product with id $id not found",
                code: 404,
            )));
        }

        return Result::success(ProductEntity::unserialize($row));
    }

    public function insert(IProduct $product): Result
    {
        $this->logger->debug("Inserting product with id $product->id");
        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) return Result::failure($result->getErrorId());
        /** @var PDO $pdo */
        $pdo = $result->getValue();
        $data = ProductEntity::map($product)->serialize();

        try {
            Insert::new($pdo)
                ->into(self::TABLE_NAME)
                ->columns($data)
                ->perform();
        } catch (Throwable $e) {
            $context = new LeafContext(
                message: 'An error occurred while trying to insert the product',
                details: new Map(['product' => (string) json_encode($data), 'error' => $e->getMessage()]),
                code: 500,
            );
            $this->logger->error($context->message, ($context->details?->toArray() ?? []));
            return Result::failure(Leaf::newError($context));
        }

        return Result::void();
    }

    public function update(IProduct $product): Result
    {
        $this->logger->debug("Updating product with id $product->id");
        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) return Result::failure($result->getErrorId());
        /** @var PDO $pdo */
        $pdo = $result->getValue();
        $entity = ProductEntity::map($product);
        $data = $entity->serialize();

        try {
            Update::new($pdo)
                ->table(self::TABLE_NAME)
                ->columns($data)
                ->where('id = :id_filter')
                ->bindValue('id_filter', Base62::decode($entity->id))
                ->perform();
        } catch (Throwable $e) {
            $context = new LeafContext(
                message: 'An error occurred while trying to update the product',
                details: new Map(['productId' => $entity->id, 'error' => $e->getMessage()]),
                code: 500,
            );
            $this->logger->error($context->message, ($context->details?->toArray() ?? []));
            return Result::failure(Leaf::newError($context));
        }

        return Result::void();
    }

    public function delete(string $id): Result
    {
        $this->logger->debug("Deleting product with id $id");
        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) return Result::failure($result->getErrorId());
        /** @var PDO $pdo */
        $pdo = $result->getValue();

        try {
            Delete::new($pdo)
                ->from(self::TABLE_NAME)
                ->where('id = :id')
                ->bindValue('id', Base62::decode($id))
                ->perform();
        } catch (Throwable $e) {
            $context = new LeafContext(
                message: 'An error occurred while trying to delete the product',
                details: new Map(['productId' => $id, 'error' => $e->getMessage()]),
                code: 500,
            );
            $this->logger->error($context->message, ($context->details?->toArray() ?? []));
            return Result::failure(Leaf::newError($context));
        }

        return Result::void();
    }
}
