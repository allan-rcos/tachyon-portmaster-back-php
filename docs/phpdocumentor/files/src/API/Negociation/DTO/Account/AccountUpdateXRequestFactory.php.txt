<?php

/**
 * Account Update Request Factory.
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

use API\Fbs\Account\AccountUpdateRequest;
use API\Negociation\IRequestAbstractFactory;
use API\Negociation\Interno\JsonHelper;
use Google\FlatBuffers\ByteBuffer;
use Shared\Exceptions\Result;

/**
 * Builds an {@see AccountUpdateXRequest} from either wire format.
 *
 * @implements IRequestAbstractFactory<AccountUpdateXRequest>
 *
 * @see \API\Negociation\DTO\Auth\LoginXRequestFactory The inbound factory shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class AccountUpdateXRequestFactory implements IRequestAbstractFactory
{
    /**
     * {@inheritDoc}
     *
     * @param  array<string, mixed>  $data  A `json_decode(..., true)` result.
     * @return Result<AccountUpdateXRequest> The hydrated message.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function fromJson(array $data): Result
    {
        return Result::success(new AccountUpdateXRequest(
            name: JsonHelper::nullableString($data, 'name'),
            email: JsonHelper::nullableString($data, 'email'),
        ));
    }

    /**
     * {@inheritDoc}
     *
     * @param  ByteBuffer  $buffer  A buffer produced against the same schema.
     * @return Result<AccountUpdateXRequest> The hydrated message.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function fromFlatbuffer(ByteBuffer $buffer): Result
    {
        $table = AccountUpdateRequest::getRootAsAccountUpdateRequest($buffer);

        return Result::success(new AccountUpdateXRequest(
            name: $table->getName(),
            email: $table->getEmail(),
        ));
    }
}
