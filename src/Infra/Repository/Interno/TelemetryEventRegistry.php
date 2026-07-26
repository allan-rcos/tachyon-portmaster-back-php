<?php

declare(strict_types=1);

namespace Infra\Repository\Interno;

use Domain\Models\Internal\TelemetryEvent;
use Domain\Models\ITelemetryEvent;
use Ds\Seq;
use Infra\Repository\ITelemetryEventRepository;
use Shared\Exceptions\Result;

/**
 * {@see ITelemetryEventRepository} over the `telemetry_events` table, filled at
 * WorkerStart.
 *
 * @extends SqlMetadataRegistry<ITelemetryEvent>
 */
final class TelemetryEventRegistry extends SqlMetadataRegistry implements ITelemetryEventRepository
{
    public function add(ITelemetryEvent $event): Result
    {
        return $this->register($event->slug);
    }

    public function getBySlug(string $slug): ?ITelemetryEvent
    {
        return $this->find($slug);
    }

    public function getById(int $id): ?ITelemetryEvent
    {
        return $this->findById($id);
    }

    /**
     * @return Seq<ITelemetryEvent>
     */
    public function all(): Seq
    {
        return $this->listAll();
    }

    protected function hydrate(string $slug, int $id): ITelemetryEvent
    {
        return new TelemetryEvent($slug, $id);
    }

    protected function label(): string
    {
        return 'telemetry event';
    }

    protected function table(): string
    {
        return 'telemetry_events';
    }
}
