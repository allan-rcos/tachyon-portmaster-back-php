<?php

/**
 * Manifest Cargo Entity.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

namespace Infra\Entity;

use Domain\ID\Base62;
use Domain\Models\IManifestCargo;

/**
 * Maps a `container_items` row to/from an {@see IManifestCargo}. Keeps the raw
 * `$row` array out of the repository: the persisted ids are integers, the
 * domain model carries them Base62-encoded.
 *
 * Follows the entity shape documented on {@see ProductEntity}, with two
 * differences: the row is keyed by both ids together, and there is no derived
 * column — a cargo line has nothing searchable of its own.
 *
 * @see IManifestCargo The contract it satisfies.
 * @see ProductEntity The shape this follows.
 * @see \Infra\Repository\Interno\SqlManifestRepository What maps through it.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class ManifestCargoEntity implements IManifestCargo
{
    /**
     * @param  string  $containerId  Base62 id of the container holding it.
     * @param  string  $productId  Base62 id of what is held. Together with
     *                             `$containerId` this is the row's whole
     *                             identity.
     * @param  float  $quantity  How many units.
     * @param  float  $weight  What that quantity weighs.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public string $containerId,
        public string $productId,
        public float $quantity,
        public float $weight,
    ) {
    }

    /**
     * Adopts any {@see IManifestCargo} into this entity so it can be serialised.
     *
     * @param  IManifestCargo  $cargo  Whatever the domain built.
     * @return self A copy, ready to write.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function map(IManifestCargo $cargo): self
    {
        return new self(
            containerId: $cargo->containerId,
            productId: $cargo->productId,
            quantity: $cargo->quantity,
            weight: $cargo->weight,
        );
    }

    /**
     * Builds a cargo line from a stored row, encoding both ids back to Base62.
     *
     * Every column is coerced, so a row missing one produces a usable line
     * rather than a type error.
     *
     * @param  array<string, mixed>  $row  As the driver returned it.
     * @return self The line as the domain expects it.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function unserialize(array $row): self
    {
        $containerId = $row['container_id'] ?? 0;
        $productId   = $row['product_id'] ?? 0;

        return new self(
            containerId: Base62::encode(is_numeric($containerId) ? (int) $containerId : 0),
            productId: Base62::encode(is_numeric($productId) ? (int) $productId : 0),
            quantity: is_numeric($row['quantity'] ?? null) ? (float) $row['quantity'] : 0.0,
            weight: is_numeric($row['weight'] ?? null) ? (float) $row['weight'] : 0.0,
        );
    }

    /**
     * Produces the row to write, decoding both ids to their integer form.
     *
     * The repository reads the two id columns straight off this array rather
     * than decoding again, which is why they must be the decoded values here.
     *
     * @return array<string, mixed> Column names to values.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function serialize(): array
    {
        return [
            'container_id' => Base62::decode($this->containerId),
            'product_id' => Base62::decode($this->productId),
            'quantity' => $this->quantity,
            'weight' => $this->weight,
        ];
    }
}
