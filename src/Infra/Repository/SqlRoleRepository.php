<?php

namespace Infra\Repository;

use Atlas\Query\Delete;
use Atlas\Query\Insert;
use Atlas\Query\Select;
use Atlas\Query\Update;
use Domain\Models\IRole;
use Domain\Ports\Core\IUnitOfWork;
use Domain\Ports\Repository\IRoleRepository;
use Ds\Map;
use Infra\Entity\RoleEntity;
use PDO;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;
use Shared\Logging\ILogger;
use Throwable;

final readonly class SqlRoleRepository implements IRoleRepository
{
    private const string TABLE_NAME = 'roles';
    private ILogger $logger;

    public function __construct(
        ILogger $logger,
        private IUnitOfWork $session,
    ) {
        $this->logger = $logger->withChannel("sql-role-repository");
    }

    public function findById(int $id): Result
    {
        $this->logger->debug("Finding role with id $id");
        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) return $result;
        $pdo = $result->getValue();

        try {
            $row = Select::new($pdo)
                ->columns("*")
                ->from(self::TABLE_NAME)
                ->where("id = :id")
                ->bindValue("id", $id)
                ->fetchOne();
        } catch (Throwable $e) {
            $context = new LeafContext(
                message: "An error occurred while trying to find the role",
                details: new Map([
                    "roleId" => $id,
                    "error" => $e->getMessage(),
                ]),
                code: 500,
            );
            $this->logger->error($context->message,
                $context->details->toArray());
            return Result::failure(Leaf::newError($context));
        }

        if (!$row) {
            $context = new LeafContext(
                message: "Role not found",
                details: new Map([
                    "roleId" => $id,
                ]),
                code: 404,
            );
            $this->logger->info($context->message,
                $context->details->toArray());
            return Result::failure(Leaf::newError($context));
        }
        return Result::success(RoleEntity::unserialize($row));
    }

    public function delete(int $id): Result
    {
        $this->logger->debug("Deleting role with id $id");
        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) return $result;
        /** @var PDO $pdo */
        $pdo = $result->getValue();

        try {
            $result = Delete::new($pdo)
                ->from(self::TABLE_NAME)
                ->where('id = :id')
                ->bindValue('id', $id)
                ->perform();

            if ($result->rowCount() <= 0) {
                $this->logger->debug("Role not found for deletion, no rows affected");
            }
        } catch (Throwable $e) {
            $context = new LeafContext(
                message: "An error occurred while trying to delete the role",
                details: new Map([
                    "roleId" => $id,
                    "error" => $e->getMessage(),
                ]),
                code: 500,
            );
            $this->logger->error($context->message,
                $context->details->toArray());
            return Result::failure(Leaf::newError($context));
        }

        return Result::void();
    }

    public function insert(IRole $role): Result
    {
        $this->logger->debug("Inserting role with id $role->id");
        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) return $result;
        /** @var PDO $pdo */
        $pdo = $result->getValue();
        $entity = RoleEntity::map($role);
        $data = $entity->serialize();

        try {
            Insert::new($pdo)
                ->into(self::TABLE_NAME)
                ->columns($data)
                ->perform();
        } catch (Throwable $e) {
            $context = new LeafContext(
                message: "An error occurred while trying to insert the role",
                details: new Map([
                    "role" => $data,
                    "error" => $e->getMessage(),
                ]),
                code: 500,
            );
            $this->logger->error($context->message,
                $context->details->toArray());
            return Result::failure(Leaf::newError($context));
        }

        return Result::void();
    }

    public function update(IRole $role): Result
    {
        $this->logger->debug("Updating role with id $role->id");
        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) return $result;
        /** @var PDO $pdo */
        $pdo = $result->getValue();
        $entity = RoleEntity::map($role);
        $data = $entity->serialize();

        try {
            $result = Update::new($pdo)
                ->table(self::TABLE_NAME)
                ->columns($data)
                ->where('id = :id_filter')
                ->bindValue('id_filter', $entity->id)
                ->perform();

            if ($result->rowCount() <= 0) {
                $this->logger->debug("Role not found for update, no rows affected");
            }
        } catch (Throwable $e) {
            $context = new LeafContext(
                message: "An error occurred while trying to update the role",
                details: new Map([
                    "roleId" => $entity->id,
                    "error" => $e->getMessage(),
                ]),
                code: 500,
            );
            $this->logger->error($context->message,
                $context->details->toArray());
            return Result::failure(Leaf::newError($context));
        }
        return Result::void();
    }
}