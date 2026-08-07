<?php

/**
 * User List Response Factory.
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

use API\Fbs\Admin\UserListResponse;
use API\Negociation\IResponseAbstractFactory;
use Google\FlatBuffers\FlatbufferBuilder;
use Shared\Exceptions\Result;

/**
 * Renders a {@see UserListXResponse} in either wire format.
 *
 * @see \API\Negociation\DTO\Product\ProductListXResponseFactory How a vector is built.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class UserListXResponseFactory implements IResponseAbstractFactory
{
    /**
     * @var list<IResponseAbstractFactory> One factory per data row, wrapped
     *                                     once, in the order the message holds
     *                                     them.
     */
    private array $dataFactories;

    /**
     * @param  UserListXResponse  $message  The message to render.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(private UserListXResponse $message)
    {
        $this->dataFactories = array_map(
            static fn (UserAdminXResponse $item): IResponseAbstractFactory => new UserAdminXResponseFactory($item),
            $message->data,
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
        $itemOffsets = array_map(
            static fn (IResponseAbstractFactory $item): int => $item->createFlatbuffer($builder)->getValue(),
            $this->dataFactories,
        );
        $data = UserListResponse::createDataVector($builder, $itemOffsets);

        return Result::success(UserListResponse::createUserListResponse($builder, $data));
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
            'data' => array_map(
                static fn (IResponseAbstractFactory $item): array => $item->createJson()->getValue(),
                $this->dataFactories,
            ),
        ]);
    }
}
