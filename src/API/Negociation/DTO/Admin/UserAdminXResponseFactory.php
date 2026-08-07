<?php

/**
 * User Admin Response Factory.
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

use API\Fbs\Admin\UserAdminResponse;
use API\Negociation\DTO\Account\RoleXResponse;
use API\Negociation\DTO\Account\RoleXResponseFactory;
use API\Negociation\IResponseAbstractFactory;
use Google\FlatBuffers\FlatbufferBuilder;
use Shared\Exceptions\Result;

/**
 * Renders a {@see UserAdminXResponse} in either wire format.
 *
 * @see \API\Negociation\DTO\Auth\UserXFactory The outbound factory shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class UserAdminXResponseFactory implements IResponseAbstractFactory
{
    /**
     * @var list<IResponseAbstractFactory> One factory per roles row, wrapped
     *                                     once, in the order the message holds
     *                                     them.
     */
    private array $rolesFactories;

    /**
     * @param  UserAdminXResponse  $message  The message to render.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(private UserAdminXResponse $message)
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
        $roles = UserAdminResponse::createRolesVector($builder, $roleOffsets);

        $name  = $this->message->name !== null ? $builder->createString($this->message->name) : 0;
        $email = $this->message->email !== null ? $builder->createString($this->message->email) : 0;

        return Result::success(UserAdminResponse::createUserAdminResponse($builder, $id, $name, $email, $roles));
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
