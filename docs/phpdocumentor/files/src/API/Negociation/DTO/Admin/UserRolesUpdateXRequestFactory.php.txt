<?php

/**
 * User Roles Update Request Factory.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation\DTO\Admin;

use API\Fbs\Admin\UserRolesUpdateRequest;
use API\Negociation\IRequestAbstractFactory;
use API\Negociation\Interno\JsonHelper;
use Google\FlatBuffers\ByteBuffer;
use Shared\Exceptions\Result;

/**
 * Builds a {@see UserRolesUpdateXRequest} from either wire format.
 *
 * @implements IRequestAbstractFactory<UserRolesUpdateXRequest>
 *
 * @see RolePermissionsUpdateXRequestFactory The sibling this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class UserRolesUpdateXRequestFactory implements IRequestAbstractFactory
{
    /**
     * {@inheritDoc}
     *
     * @param  array<string, mixed>  $data  A `json_decode(..., true)` result.
     * @return Result<UserRolesUpdateXRequest> The hydrated message.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function fromJson(array $data): Result
    {
        return Result::success(new UserRolesUpdateXRequest(
            roleIds: self::nonEmpty(JsonHelper::stringList($data, 'role_ids')),
        ));
    }

    /**
     * {@inheritDoc}
     *
     * @param  ByteBuffer  $buffer  A buffer produced against the same schema.
     * @return Result<UserRolesUpdateXRequest> The hydrated message.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function fromFlatbuffer(ByteBuffer $buffer): Result
    {
        $table = UserRolesUpdateRequest::getRootAsUserRolesUpdateRequest($buffer);

        $roleIds = [];
        for ($i = 0, $n = $table->getRoleIdsLength(); $i < $n; $i++) {
            $roleIds[] = (string) $table->getRoleIds($i);
        }

        return Result::success(new UserRolesUpdateXRequest(roleIds: self::nonEmpty($roleIds)));
    }

    /**
     * Drops empty ids, on both branches.
     *
     * Not validation, which belongs to the domain — narrowing. `Base62::decode()`
     * refuses an empty string by throwing, and `syncRoles()` turns anything
     * thrown into a 500, so an empty entry costs the whole request rather than
     * the one id. The hand-rolled parser this factory replaces dropped them, and
     * a body that already worked keeps working.
     *
     * @param  list<string>  $ids  Ids as they arrived.
     * @return list<string> The ones worth decoding.
     *
     * @copyright 2026 Tachyon
     */
    private static function nonEmpty(array $ids): array
    {
        return array_values(array_filter($ids, static fn (string $id): bool => $id !== ''));
    }
}
