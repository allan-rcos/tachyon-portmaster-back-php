<?php

namespace Infra\Entity;

use Domain\ID\Base62;
use Domain\Models\IManifestCargo;

/**
 * Maps a `container_items` row to/from an {@see IManifestCargo}. Keeps the raw
 * `$row` array out of the repository: the persisted ids are integers, the
 * domain model carries them Base62-encoded.
 */
final readonly class ManifestCargoEntity implements IManifestCargo
{
    public function __construct(
        public string $containerId,
        public string $productId,
        public float $quantity,
        public float $weight,
    ) {
    }

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
     * @param  array<string, mixed>  $row
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
     * @return array<string, mixed>
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
