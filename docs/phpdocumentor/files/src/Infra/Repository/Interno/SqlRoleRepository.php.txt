<?php

/**
 * SQL Role Repository.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

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

/**
 * {@see IRoleRepository} over the `roles` table, joining `user_roles` to answer
 * who holds what.
 *
 * Every method opens by asking {@see IPdoTransaction} for the caller's
 * connection, so the writes land inside whatever boundary the use case opened
 * and a rollback there undoes them.
 *
 * The two mutations that could match nothing — {@see update()} and
 * {@see delete()} — check the row count only to log it. A miss is not turned
 * into a 404, because the use case that cares has already loaded the role.
 *
 * @see IRoleRepository The contract this implements.
 * @uses ILogger Records failures under the `sql-role-repository` channel.
 * @uses IPdoTransaction Supplies the caller's connection.
 * @uses RoleEntity Maps between the domain model and the row.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class SqlRoleRepository implements IRoleRepository
{
    /**
     * @var string The table every statement here addresses.
     */
    private const string TABLE_NAME = 'roles';

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
        $this->logger = $logger->withChannel("sql-role-repository");
    }

    /**
     * Loads a role by id, decoding the Base62 to match the column.
     *
     * The miss is logged at info rather than error: asking for a role that is
     * not there is a client's mistake, not the server's.
     *
     * @param  string  $id  Base62 id.
     * @return Result<IRole> A 404 failure when the select returned nothing; a
     *                       500 when it threw, which is also where an
     *                       undecodable id lands.
     *
     * @copyright 2026 Tachyon
     */
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

    /**
     * Loads a user's roles with one inner join across the `user_roles` pivot.
     *
     * Rows that are not arrays are skipped rather than allowed to become broken
     * roles, so a malformed result set narrows the answer instead of failing the
     * request.
     *
     * @param  string  $userId  Base62 id of the user.
     * @return Result<IRole[]> The roles, empty when they hold none; a 500
     *                         failure when the select threw.
     *
     * @copyright 2026 Tachyon
     */
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

    /**
     * Removes the role row, and with it — by cascade — every assignment of it.
     *
     * @param  string  $id  Base62 id.
     * @return Result<null> Void on success; a 500 failure when the delete threw.
     *                      Matching no row is only logged.
     *
     * @copyright 2026 Tachyon
     */
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

    /**
     * Writes a new role row, its permission slugs among the serialised columns.
     *
     * @param  IRole  $role  Already validated; serialised through
     *                       {@see RoleEntity}.
     * @return Result<null> Void on success; a 500 failure when the insert threw.
     *
     * @copyright 2026 Tachyon
     */
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

    /**
     * Overwrites the role row matching the id it carries, permission list
     * included.
     *
     * @param  IRole  $role  The new state, already validated.
     * @return Result<null> Void on success; a 500 failure when the update threw.
     *                      Matching no row is only logged.
     *
     * @copyright 2026 Tachyon
     */
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
