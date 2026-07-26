<?php

namespace Infra\Repository\Interno;

use Atlas\Query\Delete;
use Atlas\Query\Insert;
use Atlas\Query\Select;
use Atlas\Query\Update;
use Domain\ID\Base62;
use Domain\Models\IUser;
use Infra\Database\IPdoTransaction;
use Infra\Repository\IUserRepository;
use Ds\Map;
use Infra\Entity\UserEntity;
use PDO;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;
use Infra\Logging\ILogger;
use Throwable;

final readonly class SqlUserRepository implements IUserRepository
{
    private const string TABLE_NAME = 'users';
    private ILogger $logger;

    public function __construct(
        ILogger $logger,
        private IPdoTransaction $session,
    ) {
        $this->logger = $logger->withChannel("sql-user-repository");
    }

    /**
     * @param  string  $userId
     * @param  list<string>  $roleIds
     * @return Result<null>
     */
    public function syncRoles(string $userId, array $roleIds): Result
    {
        $this->logger->debug("Syncing roles for user $userId");
        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) return Result::failure($result->getErrorId());
        /** @var PDO $pdo */
        $pdo = $result->getValue();

        try {
            $userIdInt = Base62::decode($userId);
            Delete::new($pdo)
                ->from('user_roles')
                ->where('user_id = :user_id')
                ->bindValue('user_id', $userIdInt)
                ->perform();

            foreach (array_unique($roleIds) as $roleId) {
                Insert::new($pdo)
                    ->into('user_roles')
                    ->columns(['user_id' => $userIdInt, 'role_id' => Base62::decode($roleId)])
                    ->perform();
            }
        } catch (Throwable $e) {
            $context = new LeafContext(
                message: "An error occurred while syncing the user's roles",
                details: new Map(['userId' => $userId, 'error' => $e->getMessage()]),
                code: 500,
            );
            $this->logger->error($context->message, ($context->details?->toArray() ?? []));
            return Result::failure(Leaf::newError($context));
        }

        return Result::void();
    }

    /**
     * @return Result<IUser>
     */
    public function findById(string $id): Result
    {
        return $this->findBy(Base62::decode($id), "id");
    }

    /**
     * @return Result<bool>
     */
    public function hasAny(): Result
    {
        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) return Result::failure($result->getErrorId());
        /** @var PDO $pdo */
        $pdo = $result->getValue();

        try {
            // LIMIT 1 rather than COUNT(*): the question is existence, and the
            // table can be large by the time anyone asks it again.
            $row = Select::new($pdo)
                ->columns('1')
                ->from(self::TABLE_NAME)
                ->limit(1)
                ->fetchOne();
        } catch (Throwable $e) {
            $context = new LeafContext(
                message: 'An error occurred while checking whether any user exists',
                details: new Map(['error' => $e->getMessage()]),
                code: 500,
            );
            $this->logger->error($context->message, ($context->details?->toArray() ?? []));

            return Result::failure(Leaf::newError($context));
        }

        return Result::success(is_array($row));
    }

    /**
     * @param  int|string  $value
     * @param  string  $column
     * @return Result<IUser>
     */
    public function findBy(int|string $value, string $column): Result
    {
        $this->logger->debug("Finding user with $column $value");
        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) return Result::failure($result->getErrorId());
        $pdo = $result->getValue();

        try {
            $row = Select::new($pdo)
                ->columns("*")
                ->from(self::TABLE_NAME)
                ->where("$column = :value")
                ->bindValue("value", $value)
                ->fetchOne();
        } catch (Throwable $e) {
            $context = new LeafContext(
                message: "An error occurred while trying to find the user",
                details: new Map([
                    "column" => $column,
                    "value" => (string) $value,
                    "error" => $e->getMessage(),
                ]),
                code: 500,
            );
            $this->logger->error($context->message,
                ($context->details?->toArray() ?? []));
            return Result::failure(Leaf::newError($context));
        }

        if (!$row) {
            $this->logger->info("User not found", [
                "column" => $column,
                "value" => $value,
            ]);
            return Result::failure(Leaf::newError(new LeafContext(
                message: "User with id $value not found",
                code: 404,
            )));
        }
        return Result::success(UserEntity::unserialize($row));
    }

    public function findByEmail(string $email): Result
    {
        return $this->findBy(strtolower($email), "email");
    }

    public function delete(string $id): Result
    {
        $this->logger->debug("Deleting user with id $id");
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
                $this->logger->debug("User not found for deletion, no rows affected");
            }
        } catch (Throwable $e) {
            $context = new LeafContext(
                message: "An error occurred while trying to delete the user",
                details: new Map([
                    "userId" => $id,
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

    public function insert(IUser $user): Result
    {
        $this->logger->debug("Inserting user with id $user->id");
        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) return Result::failure($result->getErrorId());
        /** @var PDO $pdo */
        $pdo = $result->getValue();
        $entity = UserEntity::map($user);
        $data = $entity->serialize();

        try {
            Insert::new($pdo)
                ->into(self::TABLE_NAME)
                ->columns($data)
                ->perform();
        } catch (Throwable $e) {
            $context = new LeafContext(
                message: "An error occurred while trying to insert the user",
                details: new Map([
                    "user" => (string) json_encode($data),
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

    public function update(IUser $user): Result
    {
        $this->logger->debug("Updating role with id $user->id");
        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) return Result::failure($result->getErrorId());
        /** @var PDO $pdo */
        $pdo = $result->getValue();
        $entity = UserEntity::map($user);
        $data = $entity->serialize();

        try {
            $result = Update::new($pdo)
                ->table(self::TABLE_NAME)
                ->columns($data)
                ->where('id = :id_filter')
                ->bindValue('id_filter', Base62::decode($entity->id))
                ->perform();

            if ($result->rowCount() <= 0) {
                $this->logger->debug("User not found for update, no rows affected");
            }
        } catch (Throwable $e) {
            $context = new LeafContext(
                message: "An error occurred while trying to update the user",
                details: new Map([
                    "userId" => $entity->id,
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