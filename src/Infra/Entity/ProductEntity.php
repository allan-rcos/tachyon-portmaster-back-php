<?php

namespace Infra\Entity;

use Domain\Enums\RiskClass;
use Domain\ID\Base62;
use Domain\Models\IProduct;
use Infra\Text\SearchKey;

/**
 * Persistence view of a product. Carries an extra `search_name` column (not on
 * the domain model) so name searches match accent/case-insensitively.
 */
class ProductEntity implements IProduct
{
    public function __construct(
        public string $id {
            get => $this->id;
        },
        public string $name {
            get => $this->name;
        },
        public float $density {
            get => $this->density;
        },
        public RiskClass $riskClass {
            get => $this->riskClass;
        },
    ) {
    }

    public static function map(IProduct $product): self
    {
        return new self(
            id: $product->id,
            name: $product->name,
            density: $product->density,
            riskClass: $product->riskClass,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function unserialize(array $row): self
    {
        $id = $row['id'] ?? 0;
        $name = $row['name'] ?? '';
        $density = $row['density'] ?? 0.0;
        $riskClass = $row['risk_class'] ?? '';

        return new self(
            id: Base62::encode(is_numeric($id) ? (int) $id : 0),
            name: is_scalar($name) ? (string) $name : '',
            density: is_numeric($density) ? (float) $density : 0.0,
            riskClass: RiskClass::tryFrom(is_scalar($riskClass) ? (string) $riskClass : '') ?? RiskClass::Class1Explosives,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(): array
    {
        return [
            'id' => Base62::decode($this->id),
            'name' => $this->name,
            'density' => $this->density,
            'risk_class' => $this->riskClass->value,
            'search_name' => SearchKey::of($this->name),
        ];
    }
}
