<?php

/**
 * User Create Request Factory.
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

use API\Fbs\Admin\UserCreateRequest;
use API\Negociation\IRequestAbstractFactory;
use API\Negociation\Interno\JsonHelper;
use Google\FlatBuffers\ByteBuffer;
use Shared\Exceptions\Result;

/**
 * Builds a {@see UserCreateXRequest} from either wire format.
 *
 * @implements IRequestAbstractFactory<UserCreateXRequest>
 *
 * @see \API\Negociation\DTO\Auth\LoginXRequestFactory The inbound factory shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class UserCreateXRequestFactory implements IRequestAbstractFactory
{
    /**
     * {@inheritDoc}
     *
     * @param  array<string, mixed>  $data  A `json_decode(..., true)` result.
     * @return Result<UserCreateXRequest> The hydrated message.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function fromJson(array $data): Result
    {
        return Result::success(new UserCreateXRequest(
            name: JsonHelper::nullableString($data, 'name'),
            email: JsonHelper::nullableString($data, 'email'),
            initialPassword: JsonHelper::nullableString($data, 'initial_password'),
            roleIds: JsonHelper::stringList($data, 'role_ids'),
        ));
    }

    /**
     * {@inheritDoc}
     *
     * @param  ByteBuffer  $buffer  A buffer produced against the same schema.
     * @return Result<UserCreateXRequest> The hydrated message.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function fromFlatbuffer(ByteBuffer $buffer): Result
    {
        $table = UserCreateRequest::getRootAsUserCreateRequest($buffer);

        $roleIds = [];
        for ($i = 0, $n = $table->getRoleIdsLength(); $i < $n; $i++) {
            $roleIds[] = (string) $table->getRoleIds($i);
        }

        return Result::success(new UserCreateXRequest(
            name: $table->getName(),
            email: $table->getEmail(),
            initialPassword: $table->getInitialPassword(),
            roleIds: $roleIds,
        ));
    }
}
