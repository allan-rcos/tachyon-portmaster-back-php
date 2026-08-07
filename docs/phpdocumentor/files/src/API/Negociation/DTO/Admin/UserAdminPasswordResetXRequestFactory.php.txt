<?php

/**
 * User Admin Password Reset Request Factory.
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

use API\Fbs\Admin\UserAdminPasswordResetRequest;
use API\Negociation\IRequestAbstractFactory;
use API\Negociation\Interno\JsonHelper;
use Google\FlatBuffers\ByteBuffer;
use Shared\Exceptions\Result;

/**
 * Builds a {@see UserAdminPasswordResetXRequest} from either wire format.
 *
 * @implements IRequestAbstractFactory<UserAdminPasswordResetXRequest>
 *
 * @see \API\Negociation\DTO\Auth\LoginXRequestFactory The inbound factory shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class UserAdminPasswordResetXRequestFactory implements IRequestAbstractFactory
{
    /**
     * {@inheritDoc}
     *
     * @param  array<string, mixed>  $data  A `json_decode(..., true)` result.
     * @return Result<UserAdminPasswordResetXRequest> The hydrated message.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function fromJson(array $data): Result
    {
        return Result::success(new UserAdminPasswordResetXRequest(
            newPassword: JsonHelper::nullableString($data, 'new_password'),
        ));
    }

    /**
     * {@inheritDoc}
     *
     * @param  ByteBuffer  $buffer  A buffer produced against the same schema.
     * @return Result<UserAdminPasswordResetXRequest> The hydrated message.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function fromFlatbuffer(ByteBuffer $buffer): Result
    {
        $table = UserAdminPasswordResetRequest::getRootAsUserAdminPasswordResetRequest($buffer);

        return Result::success(new UserAdminPasswordResetXRequest(newPassword: $table->getNewPassword()));
    }
}
