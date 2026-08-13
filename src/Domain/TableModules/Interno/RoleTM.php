<?php

/**
 * Role Table Module.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

namespace Domain\TableModules\Interno;


use Domain\Models\Internal\Role;
use Domain\Models\IRole;
use Domain\ID\IDatabaseIdGenerator;
use Domain\TableModules\IRoleTM;
use Ds\Map;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

/**
 * Builds validated roles, and replaces their permission sets.
 *
 * Only the name is validated. Permission slugs are carried through untouched:
 * their shape is {@see PermissionTM}'s business and their existence is the
 * registry's, and this module can see neither.
 *
 * @see IRoleTM The contract.
 * @see IDatabaseIdGenerator Supplies the id for a new role.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
readonly final class RoleTM implements IRoleTM
{
    /**
     * @var int Matches the `VARCHAR(255)` the name column is declared as.
     */
    private const int MAX_NAME_LENGTH = 255;

    /**
     * @param  IDatabaseIdGenerator  $idGenerator  Snowflake generator — a role id
     *                                             becomes a primary key.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IDatabaseIdGenerator $idGenerator,
    ) {
    }

    /**
     * Builds a new role, assigning it an id.
     *
     * @param  string  $name  Display name; required, at most 255 characters.
     * @param  list<string>  $permissions  Permission slugs to grant.
     * @return Result<IRole> A 422 failure when the name breaks a rule.
     *
     * @copyright 2026 Tachyon
     */
    public function create(
        string $name,
        array $permissions,
    ): Result {
        $errors = $this->validate(
            name: $name,
        );

        if (!$errors->isEmpty()) {
            $leaf = new LeafContext(
                "Validation errors",
                $errors,
                422,
            );
            return Result::failure(Leaf::newError($leaf));
        }

        return Result::success(new Role(
            id: $this->idGenerator->generate(),
            name: $name,
            permissions: $permissions,
        ));
    }

    /**
     * Produces the role with its permission set replaced.
     *
     * Nothing to validate — the name is untouched and the slugs are not this
     * module's to judge — so this cannot fail. It still returns a
     * {@see Result} to match every other table-module method, so a caller need
     * not remember which ones can fail.
     *
     * @param  IRole  $role  The role being modified.
     * @param  list<string>  $permissions  The complete new set of slugs.
     * @return Result<IRole> Always a success.
     *
     * @copyright 2026 Tachyon
     */
    public function updatePermissions(IRole $role, array $permissions): Result
    {
        return Result::success(new Role(
            id: $role->id,
            name: $role->name,
            permissions: $permissions,
        ));
    }

    /**
     * Every rule a role name must satisfy.
     *
     * @param  string  $name  Display name.
     * @return Map<string, string> Field name to message; empty when valid.
     *
     * @copyright 2026 Tachyon
     */
    private function validate(string $name): Map
    {
        /** @var Map<string, string> $errors */
        $errors = new Map();

        if (empty($name)) {
            $errors->put('name', 'Name is required.');
        } elseif (strlen($name) > self::MAX_NAME_LENGTH) {
            $errors->put('name',
                "Name must not exceed ".self::MAX_NAME_LENGTH." characters.");
        }

        return $errors;
    }
}
