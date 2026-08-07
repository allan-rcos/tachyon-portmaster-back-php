<?php

/**
 * Metrics Response Factory.
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

use API\Fbs\Metrics\MetricsResponse;
use API\Negociation\IResponseAbstractFactory;
use Google\FlatBuffers\FlatbufferBuilder;
use Shared\Exceptions\Result;

/**
 * Renders a {@see MetricsXResponse} in either wire format.
 *
 * @see \API\Negociation\DTO\Auth\UserXFactory The outbound factory shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class MetricsXResponseFactory implements IResponseAbstractFactory
{
    /**
     * @var IResponseAbstractFactory|null The nested occupancy division, wrapped once — a
     *                                    message is rendered as often as it is
     *                                    asked for, its factory built only here.
     */
    private ?IResponseAbstractFactory $occupancyDivisionFactory;

    /**
     * @param  MetricsXResponse  $message  The message to render.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(private MetricsXResponse $message)
    {
        $this->occupancyDivisionFactory = $message->occupancyDivision !== null
            ? new OccupancyDivisionXFactory($message->occupancyDivision)
            : null;
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
        $occupancyDivision = 0;
        if ($this->occupancyDivisionFactory !== null) {
            $occupancyDivision = $this->occupancyDivisionFactory->createFlatbuffer($builder)->getValue();
        }

        return Result::success(MetricsResponse::createMetricsResponse(
            $builder,
            $this->message->activeContainers,
            $this->message->totalContainers,
            $this->message->yardLoad,
            $this->message->registeredProducts,
            $occupancyDivision,
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
            'active_containers'   => $this->message->activeContainers,
            'total_containers'    => $this->message->totalContainers,
            'yard_load'           => $this->message->yardLoad,
            'registered_products' => $this->message->registeredProducts,
            'occupancy_division'  => $this->occupancyDivisionFactory !== null ? $this->occupancyDivisionFactory->createJson()->getValue() : null,
        ]);
    }
}
