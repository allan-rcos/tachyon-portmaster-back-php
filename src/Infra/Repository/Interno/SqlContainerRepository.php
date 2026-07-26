<?php

declare(strict_types=1);

namespace Infra\Repository\Interno;

use Atlas\Query\Delete;
use Atlas\Query\Insert;
use Atlas\Query\Select;
use Atlas\Query\Update;
use Domain\ID\Base62;
use Domain\Models\IContainer;
use Infra\Database\IPdoTransaction;
use Infra\Entity\ContainerEntity;
use Infra\Repository\IContainerRepository;
use Ds\Map;
use PDO;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;
use Infra\Logging\ILogger;
use Throwable;

final readonly class SqlContainerRepository implements IContainerRepository
{
    private const string TABLE_NAME = 'containers';
    private ILogger $logger;

    public function __construct(
        ILogger $logger,
        private IPdoTransaction $session,
    ) {
        $this->logger = $logger->withChannel('sql-container-repository');
    }

    public function findById(string $id): Result
    {
        $this->logger->debug("Finding container with id $id");
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
                message: 'An error occurred while trying to find the container',
                details: new Map(['containerId' => $id, 'error' => $e->getMessage()]),
                code: 500,
            );
            $this->logger->error($context->message, ($context->details?->toArray() ?? []));
            return Result::failure(Leaf::newError($context));
        }

        if (!$row) {
            return Result::failure(Leaf::newError(new LeafContext(
                message: "Container with id $id not found",
                code: 404,
            )));
        }

        return Result::success(ContainerEntity::unserialize($row));
    }

    public function insert(IContainer $container): Result
    {
        $this->logger->debug("Inserting container with id $container->id");
        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) return Result::failure($result->getErrorId());
        /** @var PDO $pdo */
        $pdo = $result->getValue();
        $data = ContainerEntity::map($container)->serialize();

        try {
            Insert::new($pdo)
                ->into(self::TABLE_NAME)
                ->columns($data)
                ->perform();
        } catch (Throwable $e) {
            $context = new LeafContext(
                message: 'An error occurred while trying to insert the container',
                details: new Map(['container' => (string) json_encode($data), 'error' => $e->getMessage()]),
                code: 500,
            );
            $this->logger->error($context->message, ($context->details?->toArray() ?? []));
            return Result::failure(Leaf::newError($context));
        }

        return Result::void();
    }

    public function update(IContainer $container): Result
    {
        $this->logger->debug("Updating container with id $container->id");
        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) return Result::failure($result->getErrorId());
        /** @var PDO $pdo */
        $pdo = $result->getValue();
        $entity = ContainerEntity::map($container);
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
                message: 'An error occurred while trying to update the container',
                details: new Map(['containerId' => $entity->id, 'error' => $e->getMessage()]),
                code: 500,
            );
            $this->logger->error($context->message, ($context->details?->toArray() ?? []));
            return Result::failure(Leaf::newError($context));
        }

        return Result::void();
    }

    public function delete(string $id): Result
    {
        $this->logger->debug("Deleting container with id $id");
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
                message: 'An error occurred while trying to delete the container',
                details: new Map(['containerId' => $id, 'error' => $e->getMessage()]),
                code: 500,
            );
            $this->logger->error($context->message, ($context->details?->toArray() ?? []));
            return Result::failure(Leaf::newError($context));
        }

        return Result::void();
    }
}
