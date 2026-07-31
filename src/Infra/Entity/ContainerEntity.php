<?php

/**
 * Container Entity.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

namespace Infra\Entity;

use Domain\Enums\ContainerStatus;
use Domain\ID\Base62;
use Domain\Models\IContainer;
use Infra\Text\SearchKey;

/**
 * Persistence view of a container. Carries a `search_code` column (not on the
 * domain model) so code searches match case-insensitively.
 *
 * Follows the entity shape documented on {@see ProductEntity}. The container row
 * only; its cargo lines are {@see ManifestCargoEntity}'s.
 *
 * @see IContainer The contract it satisfies.
 * @see SearchKey What derives the extra column.
 * @see ProductEntity The shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
class ContainerEntity implements IContainer
{
    /**
     * @param  string  $id  Base62, as the model carries it.
     * @param  string  $code  The container's human-facing identifier.
     * @param  float  $currentWeight  What it holds now.
     * @param  float  $maxCapacity  What it may hold.
     * @param  ContainerStatus  $status  The resolved enum, not the stored
     *                                   string.
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

    /**
     * Adopts any {@see IContainer} into this entity so it can be serialised.
     *
     * @param  IContainer  $container  Whatever the domain built.
     * @return self A copy, ready to write.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function map(IContainer $container): self
    {
        return new self(
            id: $container->id,
            code: $container->code,
            currentWeight: $container->currentWeight,
            maxCapacity: $container->maxCapacity,
            status: $container->status,
        );
    }

    /**
     * Builds a container from a stored row, encoding the id back to Base62.
     *
     * Every column is coerced; an unrecognised status degrades to
     * {@see ContainerStatus::Empty}.
     *
     * @param  array<string, mixed>  $row  As the driver returned it.
     * @return self The container as the domain expects it.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function unserialize(array $row): self
    {
        $id = $row['id'] ?? 0;
        $code = $row['code'] ?? '';
        $currentWeight = $row['current_weight'] ?? 0.0;
        $maxCapacity = $row['max_capacity'] ?? 0.0;
        $status = $row['status'] ?? '';

        return new self(
            id: Base62::encode(is_numeric($id) ? (int) $id : 0),
            code: is_scalar($code) ? (string) $code : '',
            currentWeight: is_numeric($currentWeight) ? (float) $currentWeight : 0.0,
            maxCapacity: is_numeric($maxCapacity) ? (float) $maxCapacity : 0.0,
            status: ContainerStatus::tryFrom(is_scalar($status) ? (string) $status : '') ?? ContainerStatus::Empty,
        );
    }

    /**
     * Produces the row to write, deriving `search_code` on the way out.
     *
     * @return array<string, mixed> Column names to values, the id decoded back
     *                              to its integer form and the status flattened
     *                              to its slug.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function serialize(): array
    {
        return [
            'id' => Base62::decode($this->id),
            'code' => $this->code,
            'current_weight' => $this->currentWeight,
            'max_capacity' => $this->maxCapacity,
            'status' => $this->status->value,
            'search_code' => SearchKey::of($this->code),
        ];
    }
}
