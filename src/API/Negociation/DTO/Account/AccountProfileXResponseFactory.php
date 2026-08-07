<?php

/**
 * Account Profile Response Factory.
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

use API\Fbs\Account\AccountProfileResponse;
use API\Negociation\IResponseAbstractFactory;
use Google\FlatBuffers\FlatbufferBuilder;
use Shared\Exceptions\Result;

/**
 * Renders an {@see AccountProfileXResponse} in either wire format.
 *
 * @see \API\Negociation\DTO\Auth\UserXFactory The outbound factory shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class AccountProfileXResponseFactory implements IResponseAbstractFactory
{
    /**
     * @var list<IResponseAbstractFactory> One factory per roles row, wrapped
     *                                     once, in the order the message holds
     *                                     them.
     */
    private array $rolesFactories;

    /**
     * @param  AccountProfileXResponse  $message  The message to render.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(private AccountProfileXResponse $message)
    {
        $this->rolesFactories = array_map(
            static fn (RoleXResponse $item): IResponseAbstractFactory => new RoleXResponseFactory($item),
            $message->roles,
        );
    }

    /**
     * {@inheritDoc}
     *
     * @param  FlatbufferBuilder  $builder  The caller's builder.
     * @return Result<int> This table's offset within it.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function createFlatbuffer(FlatbufferBuilder $builder): Result
    {
        $id = $this->message->id !== null ? $builder->createString($this->message->id) : 0;

        $roleOffsets = array_map(
            static fn (IResponseAbstractFactory $role): int => $role->createFlatbuffer($builder)->getValue(),
            $this->rolesFactories,
        );
        $roles = AccountProfileResponse::createRolesVector($builder, $roleOffsets);

        $name  = $this->message->name !== null ? $builder->createString($this->message->name) : 0;
        $email = $this->message->email !== null ? $builder->createString($this->message->email) : 0;

        return Result::success(AccountProfileResponse::createAccountProfileResponse($builder, $id, $name, $email, $roles));
    }

    /**
     * {@inheritDoc}
     *
     * @return Result<array<string, mixed>> Ready for `json_encode()`.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function createJson(): Result
    {
        return Result::success([
            'id'    => $this->message->id,
            'name'  => $this->message->name,
            'email' => $this->message->email,
            'roles' => array_map(
                static fn (IResponseAbstractFactory $role): array => $role->createJson()->getValue(),
                $this->rolesFactories,
            ),
        ]);
    }
}
