<?php

declare(strict_types=1);

namespace Infra\Query\Container;

final readonly class ContainerSummaryViewItem
{
    /**
     * @param  list<CargoItemView>  $manifest
     * @param  list<TelemetryLogView>  $recentLogs
     */
    public function __construct(
        public ContainerViewItem $container,
        public array $manifest,
        public array $recentLogs,
    ) {
    }
}
