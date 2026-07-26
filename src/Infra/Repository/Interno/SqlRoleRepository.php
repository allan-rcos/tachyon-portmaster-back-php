<?php

namespace Infra\Repository\Interno;

use Atlas\Query\Delete;
use Atlas\Query\Insert;
use Atlas\Query\Select;
use Atlas\Query\Update;
use Domain\ID\Base62;
use Domain\Models\IRole;
use Infra\Database\IPdoTransaction;
use Infra\Repository\IRoleRepository;
use Ds\Map;
use Infra\Entity\RoleEntity;
use PDO;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;
use Infra\Logging\ILogger;
use Throwable;

final readonly class SqlRoleRepository implements IRoleRepository
{
    private const string TABLE_NAME = 'roles';
    private ILogger $logger;

    public function __construct(
        ILogger $logger,
        private IPdoTransaction $session,
    ) {
        $this->logger = $logger->withChannel("sql-role-repository");
    }

    public function findById(string $id): Result
    {
        $this->logger->debug("Finding role with id $id");
        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) return Result::failure($result->getErrorId());
        $pdo = $result->getValue();

        try {
            $row = Select::new($pdo)
                ->columns("*")
                ->from(self::TABLE_NAME)
                ->where("id = :id")
                ->bindValue("id", Base62::decode($id))
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
                ($context->details?->toArray() ?? []));
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
                ($context->details?->toArray() ?? []));
            return Result::failure(Leaf::newError($context));
        }
        return Result::success(RoleEntity::unserialize($row));
    }

    public function findByUserId(string $userId): Result
    {
        $this->logger->debug("Finding roles for user $userId");
        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) return Result::failure($result->getErrorId());
        /** @var PDO $pdo */
        $pdo = $result->getValue();

        try {
            $rows = Select::new($pdo)
                ->columns('r.*')
                ->from(self::TABLE_NAME.' AS r')
                ->join('INNER', 'user_roles AS ur', 'ur.role_id = r.id')
                ->where('ur.user_id = :user_id')
                ->bindValue('user_id', Base62::decode($userId))
                ->fetchAll();
        } catch (Throwable $e) {
            $context = new LeafContext(
                message: "An error occurred while trying to load the user's roles",
                details: new Map([
                    "userId" => $userId,
                    "error" => $e->getMessage(),
                ]),
                code: 500,
            );
            $this->logger->error($context->message,
                ($context->details?->toArray() ?? []));
            return Result::failure(Leaf::newError($context));
        }

        $roles = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row)) {
                /** @var array<string, mixed> $row */
                $roles[] = RoleEntity::unserialize($row);
            }
        }

        return Result::success($roles);
    }

    public function delete(string $id): Result
    {
        $this->logger->debug("Deleting role with id $id");
        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) return Result::failure($result->getErrorId());
        /** @var PDO $pdo */
        $pdo = $result->getValue();

        try {
            $result = Delete::new($pdo)
                ->from(self::TABLE_NAME)
                ->where('id = :id')
                ->bindValue('id', Base62::decode($id))
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
                ($context->details?->toArray() ?? []));
            return Result::failure(Leaf::newError($context));
        }

        return Result::void();
    }

    public function insert(IRole $role): Result
    {
        $this->logger->debug("Inserting role with id $role->id");
        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) return Result::failure($result->getErrorId());
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
                    "role" => (string) json_encode($data),
                    "error" => $e->getMessage(),
                ]),
                code: 500,
            );
            $this->logger->error($context->message,
                ($context->details?->toArray() ?? []));
            return Result::failure(Leaf::newError($context));
        }

        return Result::void();
    }

    public function update(IRole $role): Result
    {
        $this->logger->debug("Updating role with id $role->id");
        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) return Result::failure($result->getErrorId());
        /** @var PDO $pdo */
        $pdo = $result->getValue();
        $entity = RoleEntity::map($role);
        $data = $entity->serialize();

        try {
            $result = Update::new($pdo)
                ->table(self::TABLE_NAME)
                ->columns($data)
                ->where('id = :id_filter')
                ->bindValue('id_filter', Base62::decode($entity->id))
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
                ($context->details?->toArray() ?? []));
            return Result::failure(Leaf::newError($context));
        }
        return Result::void();
    }
}