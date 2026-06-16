<?php

namespace Infra\Repository;

use Atlas\Query\Delete;
use Atlas\Query\Insert;
use Atlas\Query\Select;
use Atlas\Query\Update;
use Domain\Models\IUser;
use Domain\Ports\Core\IUnitOfWork;
use Domain\Ports\Repository\IUserRepository;
use Ds\Map;
use Infra\Entity\UserEntity;
use PDO;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;
use Shared\Logging\ILogger;
use Throwable;

final readonly class SqlUserRepository implements IUserRepository
{
    private const string TABLE_NAME = 'users';
    private ILogger $logger;

    public function __construct(
        ILogger $logger,
        private IUnitOfWork $session,
    ) {
        $this->logger = $logger->withChannel("sql-user-repository");
    }


    public function findById(int $id): Result
    {
        return $this->findBy($id, "id");
    }

    /**
     * @param  int  $value
     * @param  string  $column
     * @return Result
     */
    public function findBy(int $value, string $column): Result
    {
        $this->logger->debug("Finding user with $column $value");
        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) return $result;
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
                    "value" => $value,
                    "error" => $e->getMessage(),
                ]),
                code: 500,
            );
            $this->logger->error($context->message,
                $context->details->toArray());
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

    public function delete(int $id): Result
    {
        $this->logger->debug("Deleting user with id $id");
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
                $context->details->toArray());
            return Result::failure(Leaf::newError($context));
        }

        return Result::void();
    }

    public function insert(IUser $user): Result
    {
        $this->logger->debug("Inserting user with id $user->id");
        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) return $result;
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
                    "user" => $data,
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

    public function update(IUser $user): Result
    {
        $this->logger->debug("Updating role with id $user->id");
        $result = $this->session->getTransaction();
        if (!$result->isSuccess()) return $result;
        /** @var PDO $pdo */
        $pdo = $result->getValue();
        $entity = UserEntity::map($user);
        $data = $entity->serialize();

        try {
            $result = Update::new($pdo)
                ->table(self::TABLE_NAME)
                ->columns($data)
                ->where('id = :id_filter')
                ->bindValue('id_filter', $entity->id)
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
                $context->details->toArray());
            return Result::failure(Leaf::newError($context));
        }
        return Result::void();
    }
}