<?php

declare(strict_types=1);

use App\Events\MetaEvent;
use App\Queries\Metrics\GetMetricsQuery;
use App\Services\Interno\GetMetricsUseCase;
use Infra\Query\Interno\MetricsDQL;
use Infra\Query\IQueryRepository;
use Infra\Query\Metrics\MetricsView;
use Infra\Query\Metrics\OccupancyView;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * The dashboard read: several aggregates over the whole yard, in one query, and
 * the one the cache was worth adding for.
 *
 * It takes no parameters at all, so there is exactly one cache entry — and its
 * own group, because it is the only read whose numbers change on a container
 * write *and* on a product one, and sharing either group would leave it stale
 * after the other.
 */
describe('GetMetricsUseCase caching', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->registrar = registrar();
        $this->events = events();
        $this->query = new GetMetricsQuery(caller('metrics:read'));
        $this->view = new MetricsView(
            activeContainers: 2,
            totalContainers: 5,
            yardLoad: 0.4,
            registeredProducts: 3,
            occupancy: new OccupancyView(empty: 3, loading: 1, sealed: 1, inTransit: 0),
        );
    });

    it('answers from the cache without querying, and reports the hit', function () {
        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('get')->once()->andReturn(Result::success($this->view));
        $views->shouldNotReceive('put');

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldNotReceive('run');

        $useCase = new GetMetricsUseCase($queries, $views, $this->events, $this->registrar);

        $result = $useCase->execute($this->query);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue())->toBe($this->view)
            ->and($this->events->captured(MetaEvent::ViewCacheHit))->toBeTrue();
    });

    it('queries and stores on a miss, under its own group', function () {
        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('get')->once()->with(ViewCacheGroup::Metrics, Mockery::type('string'))
            ->andReturn(Result::failure(anError(404, 'miss')));
        $views->shouldReceive('put')->once()
            ->with(ViewCacheGroup::Metrics, Mockery::type('string'), $this->view)
            ->andReturn(Result::void());

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldReceive('run')->once()->with(Mockery::type(MetricsDQL::class))
            ->andReturn(Result::success($this->view));

        $useCase = new GetMetricsUseCase($queries, $views, $this->events, $this->registrar);

        $result = $useCase->execute($this->query);

        expect($result->isSuccess())->toBeTrue()
            ->and($this->events->captured(MetaEvent::ViewCacheHit))->toBeFalse();
    });

    it('never caches a failed query', function () {
        $broken = anError(500, 'connection lost');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('get')->once()->andReturn(Result::failure(anError(404, 'miss')));
        $views->shouldNotReceive('put');

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldReceive('run')->once()->andReturn(Result::failure($broken));

        $useCase = new GetMetricsUseCase($queries, $views, $this->events, $this->registrar);

        $result = $useCase->execute($this->query);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($broken);
    });

    it('refuses a caller without the permission before reading the cache', function () {
        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('get');

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldNotReceive('run');

        $useCase = new GetMetricsUseCase($queries, $views, $this->events, $this->registrar);

        $result = $useCase->execute(new GetMetricsQuery(stranger()));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(403);
    });
})->group('App', 'UseCase');
