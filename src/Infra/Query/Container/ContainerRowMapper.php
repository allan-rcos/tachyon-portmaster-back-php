<?php

declare(strict_types=1);

namespace Infra\Query\Container;

use Domain\Enums\ContainerStatus;
use Domain\ID\Base62;

/**
 * Maps a `containers` row into a {@see ContainerViewItem}. Shared by the
 * container DQLs. Accepts an optional column prefix so it can read joined rows
 * (e.g. `container_id`, `container_code`) in the summary DQL.
 */
final class ContainerRowMapper
{
    /**
     * @param  array<string, mixed>  $row
     */
    public static function item(array $row, string $prefix = ''): ContainerViewItem
    {
        $id = $row["{$prefix}id"] ?? 0;
        $code = $row["{$prefix}code"] ?? '';
        $currentWeight = $row["{$prefix}current_weight"] ?? 0.0;
        $maxCapacity = $row["{$prefix}max_capacity"] ?? 0.0;
        $status = $row["{$prefix}status"] ?? '';

        return new ContainerViewItem(
            id: Base62::encode(is_numeric($id) ? (int) $id : 0),
            code: is_scalar($code) ? (string) $code : '',
            currentWeight: is_numeric($currentWeight) ? (float) $currentWeight : 0.0,
            maxCapacity: is_numeric($maxCapacity) ? (float) $maxCapacity : 0.0,
            status: ContainerStatus::tryFrom(is_scalar($status) ? (string) $status : '') ?? ContainerStatus::Empty,
        );
    }
}
