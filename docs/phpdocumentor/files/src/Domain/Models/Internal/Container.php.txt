<?php

/**
 * Container.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

namespace Domain\Models\Internal;

use Domain\Enums\ContainerStatus;
use Domain\Models\IContainer;

/**
 * Concrete {@see IContainer}. Built only by
 * {@see \Domain\TableModules\IContainerTM}, which validates it and owns every
 * status transition.
 *
 * @see IContainer What each property means.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
class Container implements IContainer
{
    /**
     * @param  string  $id  Application-generated Snowflake.
     * @param  string  $code  Yard-facing identifier; unique across the yard.
     * @param  float  $currentWeight  Kilograms loaded — denormalised from the
     *                                cargo lines and kept in step with them.
     * @param  float  $maxCapacity  Kilograms the container can hold.
     * @param  ContainerStatus  $status  Position in the lifecycle.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public string $id {
            get => $this->id;
        },
        public string $code {
            get => $this->code;
        },
        public float $currentWeight {
            get => $this->currentWeight;
        },
        public float $maxCapacity {
            get => $this->maxCapacity;
        },
        public ContainerStatus $status {
            get => $this->status;
        },
    ) {
    }
}
