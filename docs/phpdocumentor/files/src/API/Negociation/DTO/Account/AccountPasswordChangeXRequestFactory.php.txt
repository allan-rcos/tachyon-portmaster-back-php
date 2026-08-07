<?php

/**
 * Account Password Change Request Factory.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation\DTO\Account;

use API\Fbs\Account\AccountPasswordChangeRequest;
use API\Negociation\IRequestAbstractFactory;
use API\Negociation\Interno\JsonHelper;
use Google\FlatBuffers\ByteBuffer;
use Shared\Exceptions\Result;

/**
 * Builds an {@see AccountPasswordChangeXRequest} from either wire format.
 *
 * @implements IRequestAbstractFactory<AccountPasswordChangeXRequest>
 *
 * @see \API\Negociation\DTO\Auth\LoginXRequestFactory The inbound factory shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class AccountPasswordChangeXRequestFactory implements IRequestAbstractFactory
{
    /**
     * {@inheritDoc}
     *
     * @param  array<string, mixed>  $data  A `json_decode(..., true)` result.
     * @return Result<AccountPasswordChangeXRequest> The hydrated message.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function fromJson(array $data): Result
    {
        return Result::success(new AccountPasswordChangeXRequest(
            currentPassword: JsonHelper::nullableString($data, 'current_password'),
            newPassword: JsonHelper::nullableString($data, 'new_password'),
        ));
    }

    /**
     * {@inheritDoc}
     *
     * @param  ByteBuffer  $buffer  A buffer produced against the same schema.
     * @return Result<AccountPasswordChangeXRequest> The hydrated message.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function fromFlatbuffer(ByteBuffer $buffer): Result
    {
        $table = AccountPasswordChangeRequest::getRootAsAccountPasswordChangeRequest($buffer);

        return Result::success(new AccountPasswordChangeXRequest(
            currentPassword: $table->getCurrentPassword(),
            newPassword: $table->getNewPassword(),
        ));
    }
}
