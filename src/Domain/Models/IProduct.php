<?php

/**
 * Product Contract.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

namespace Domain\Models;

use Domain\Enums\RiskClass;

/**
 * Something that can be loaded into a container.
 *
 * Immutable and behaviourless: the rules that decide whether a product may
 * exist live in the table module, and the weight a quantity of it contributes
 * is computed by the manifest from {@see $density}.
 *
 * @see \Domain\TableModules\IProductTM Builds and validates these.
 * @see \Domain\Models\Internal\Product The implementation.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IProduct
{
    /**
     * @var string Application-generated Snowflake, Base62-encoded at the edge.
     */
    public string $id {
        get;
    }

    /**
     * @var string Commercial name. Never blank — the table module refuses it.
     */
    public string $name {
        get;
    }

    /**
     * @var float Kilograms per litre. What turns a loaded quantity into the
     *            weight counted against a container's capacity.
     */
    public float $density {
        get;
    }

    /**
     * @var RiskClass UN dangerous-goods classification, or `None`. Reported in
     *                the yard metrics; nothing restricts loading by it today.
     */
    public RiskClass $riskClass {
        get;
    }
}
