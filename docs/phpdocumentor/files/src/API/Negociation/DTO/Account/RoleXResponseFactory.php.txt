<?php

/**
 * Role Response Factory.
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

use API\Fbs\Account\RoleResponse;
use API\Negociation\IResponseAbstractFactory;
use Google\FlatBuffers\FlatbufferBuilder;
use Shared\Exceptions\Result;

/**
 * Renders a {@see RoleXResponse} in either wire format.
 *
 * @see \API\Negociation\DTO\Auth\UserXFactory The outbound factory shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class RoleXResponseFactory implements IResponseAbstractFactory
{
    /**
     * @param  RoleXResponse  $message  The message to render.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(private RoleXResponse $message)
    {
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

        // A [string] vector holds offsets, so each slug must be written first.
        $permissions = RoleResponse::createPermissionsVector(
            $builder,
            array_map(static fn (string $slug): int => $builder->createString($slug), $this->message->permissions),
        );

        $name = $this->message->name !== null ? $builder->createString($this->message->name) : 0;

        return Result::success(RoleResponse::createRoleResponse($builder, $id, $name, $this->message->userCount, $permissions));
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
            'id'          => $this->message->id,
            'name'        => $this->message->name,
            'user_count'  => $this->message->userCount,
            'permissions' => $this->message->permissions,
        ]);
    }
}
