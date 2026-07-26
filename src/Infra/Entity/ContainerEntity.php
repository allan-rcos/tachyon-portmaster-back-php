<?php

namespace Infra\Entity;

use Domain\Enums\ContainerStatus;
use Domain\ID\Base62;
use Domain\Models\IContainer;
use Infra\Text\SearchKey;

/**
 * Persistence view of a container. Carries a `search_code` column (not on the
 * domain model) so code searches match case-insensitively.
 */
class ContainerEntity implements IContainer
{
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
     * @param  array<string, mixed>  $row
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
     * @return array<string, mixed>
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
