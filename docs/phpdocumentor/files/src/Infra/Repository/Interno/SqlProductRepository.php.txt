<?php

/**
 * SQL Product Repository.
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

/**
 * {@see IProductRepository} over the `products` table.
 *
 * Every method opens by asking {@see IPdoTransaction} for the caller's
 * connection, so the writes land inside whatever boundary the use case opened
 * and a rollback there undoes them. A session that has no transaction open is
 * the failure the method returns before touching SQL.
 *
 * Ids cross this boundary in both directions: Base62 on the application side,
 * the decoded integer in the column.
 *
 * @see IProductRepository The contract this implements.
 * @uses ILogger Records failures under the `sql-product-repository` channel.
 * @uses IPdoTransaction Supplies the caller's connection.
 * @uses ProductEntity Maps between the domain model and the row.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class SqlProductRepository implements IProductRepository
{
    /**
     * @var string The table every statement here addresses.
     */
    private const string TABLE_NAME = 'products';

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
        $this->logger = $logger->withChannel('sql-product-repository');
    }

    /**
     * Loads a product by id, decoding the Base62 to match the column.
     *
     * @param  string  $id  Base62 id.
     * @return Result<IProduct> A 404 failure when the select returned nothing; a
     *                          500 when it threw, which is also where an
     *                          undecodable id lands.
     *
     * @copyright 2026 Tachyon
     */
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

    /**
     * Writes a new product row.
     *
     * @param  IProduct  $product  Already validated; serialised through
     *                             {@see ProductEntity}.
     * @return Result<null> Void on success; a 500 failure when the insert threw,
     *                      which includes a constraint the database rejected.
     *
     * @copyright 2026 Tachyon
     */
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

    /**
     * Overwrites the product row matching the id it carries.
     *
     * Every column is rewritten, so the stored row is exactly the state passed
     * in.
     *
     * @param  IProduct  $product  The new state, already validated.
     * @return Result<null> Void on success; a 500 failure when the update threw.
     *                      A zero row count is *not* a failure and is not
     *                      inspected.
     *
     * @copyright 2026 Tachyon
     */
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

    /**
     * Removes the product row, and with it — by cascade — every cargo line that
     * referenced it.
     *
     * @param  string  $id  Base62 id.
     * @return Result<null> Void on success; a 500 failure when the delete threw.
     *                      Matching no row is *not* a failure.
     *
     * @copyright 2026 Tachyon
     */
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
