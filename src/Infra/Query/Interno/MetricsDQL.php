<?php

declare(strict_types=1);

namespace Infra\Query\Interno;

use Domain\Enums\ContainerStatus;
use Infra\Query\IDQL;
use Infra\Query\Metrics\MetricsView;
use Infra\Query\Metrics\OccupancyView;
use Atlas\Statement\Select;
use Infra\Query\SqlQuery;

/**
 * System-wide operational metrics, computed as a single row of correlated
 * aggregates over `containers`/`products`. A read-side aggregate — no domain.
 *
 * @implements IDQL<MetricsView>
 */
final readonly class MetricsDQL implements IDQL
{
    public function toSql(): SqlQuery
    {
        $select = Select::new('mysql');

        // Every aggregate is its own Atlas sub-select. Each status literal goes
        // through the outer select's bindInline(), which returns the placeholder
        // to splice into the sub-select — so even these fixed enum values are
        // bound rather than interpolated, and all binds live on one statement.
        $count = static fn (): Select => Select::new('mysql')->columns('COUNT(*)')->from('containers');

        $totalContainers = Select::new('mysql')->columns('COUNT(*)')->from('containers')->getQueryString();
        $activeContainers = $count()
            ->where('status <> '.$select->bindInline(ContainerStatus::Empty->value))->getQueryString();
        $yardLoad = Select::new('mysql')->columns('COALESCE(SUM(current_weight), 0)')->from('containers')->getQueryString();
        $registeredProducts = Select::new('mysql')->columns('COUNT(*)')->from('products')->getQueryString();
        $occEmpty = $count()
            ->where('status = '.$select->bindInline(ContainerStatus::Empty->value))->getQueryString();
        $occLoading = $count()
            ->where('status = '.$select->bindInline(ContainerStatus::Loading->value))->getQueryString();
        $occSealed = $count()
            ->where('status = '.$select->bindInline(ContainerStatus::Sealed->value))->getQueryString();
        $occInTransit = $count()
            ->where('status = '.$select->bindInline(ContainerStatus::InTransit->value))->getQueryString();

        $select->columns(
            '('.$totalContainers.') AS total_containers',
            '('.$activeContainers.') AS active_containers',
            '('.$yardLoad.') AS yard_load',
            '('.$registeredProducts.') AS registered_products',
            '('.$occEmpty.') AS occ_empty',
            '('.$occLoading.') AS occ_loading',
            '('.$occSealed.') AS occ_sealed',
            '('.$occInTransit.') AS occ_in_transit',
        );

        return new SqlQuery($select->getQueryString(), $select->getBindValueArrays());
    }

    public function hydrate(array $rows): MetricsView
    {
        $row = $rows[0] ?? [];

        return new MetricsView(
            activeContainers: $this->int($row['active_containers'] ?? null),
            totalContainers: $this->int($row['total_containers'] ?? null),
            yardLoad: is_numeric($row['yard_load'] ?? null) ? (float) $row['yard_load'] : 0.0,
            registeredProducts: $this->int($row['registered_products'] ?? null),
            occupancy: new OccupancyView(
                empty: $this->int($row['occ_empty'] ?? null),
                loading: $this->int($row['occ_loading'] ?? null),
                sealed: $this->int($row['occ_sealed'] ?? null),
                inTransit: $this->int($row['occ_in_transit'] ?? null),
            ),
        );
    }

    private function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
