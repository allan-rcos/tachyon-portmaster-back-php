<?php

/**
 * Occupancy Division Factory.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation\DTO\Metrics;

use API\Fbs\Metrics\OccupancyDivision;
use API\Negociation\IResponseAbstractFactory;
use Google\FlatBuffers\FlatbufferBuilder;
use Shared\Exceptions\Result;

/**
 * Renders an {@see OccupancyDivisionX} in either wire format.
 *
 * @see \API\Negociation\DTO\Auth\UserXFactory The outbound factory shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class OccupancyDivisionXFactory implements IResponseAbstractFactory
{
    /**
     * @param  OccupancyDivisionX  $message  The message to render.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(private OccupancyDivisionX $message)
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
        return Result::success(OccupancyDivision::createOccupancyDivision(
            $builder,
            $this->message->empty,
            $this->message->loading,
            $this->message->sealed,
            $this->message->inTransit,
        ));
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
            'empty'      => $this->message->empty,
            'loading'    => $this->message->loading,
            'sealed'     => $this->message->sealed,
            'in_transit' => $this->message->inTransit,
        ]);
    }
}
