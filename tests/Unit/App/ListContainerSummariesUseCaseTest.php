<?php

declare(strict_types=1);

use App\Events\MetaEvent;
use App\Queries\Container\ListContainerSummariesQuery;
use App\Services\Interno\ListContainerSummariesUseCase;
use Domain\Enums\ContainerStatus;
use Ds\Seq;
use Infra\Query\Container\ContainerSummaryListView;
use Infra\Query\Container\ContainerSummaryViewItem;
use Infra\Query\Container\ContainerViewItem;
use Infra\Query\Interno\ListContainerSummariesDQL;
use Infra\Query\IQueryRepository;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * The heaviest read in the application — container, manifest and telemetry in
 * one shape — which is exactly why it is cached.
 *
 * It shares the `Container` cache group with the plain listing, so a write to a
 * container drops both. The `id` filter is what narrows it to one summary, and
 * it must key the cache: without that, asking for one container's summary would
 * be answered with whichever was asked for first.
 */
describe('ListContainerSummariesUseCase caching', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->registrar = registrar();
        $this->events = events();
        $this->query = new ListContainerSummariesQuery(caller('container:summary'), limit: 10);
        $this->view = new ContainerSummaryListView(
            items: new Seq([
                new ContainerSummaryViewItem(
                    container: new ContainerViewItem('C1', 'BOX-1', 30.0, 100.0,
                        ContainerStatus::Loading),
                    manifest: [],
                    recentLogs: [],
                ),
            ]),
            nextCursor: null,
            total: 1,
        );
    });

    it('answers from the cache without querying, and reports the hit', function () {
        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('get')->once()->andReturn(Result::success($this->view));
        $views->shouldNotReceive('put');

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldNotReceive('run');

        $useCase = new ListContainerSummariesUseCase($queries, $views, $this->events,
            $this->registrar);

        $result = $useCase->execute($this->query);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue())->toBe($this->view)
            ->and($this->events->captured(MetaEvent::ViewCacheHit))->toBeTrue();
    });

    it('queries and stores on a miss, under the container group', function () {
        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('get')->once()->andReturn(Result::failure(anError(404, 'miss')));
        // The same group as the plain listing: one write drops both.
        $views->shouldReceive('put')->once()
            ->with(ViewCacheGroup::Container, Mockery::type('string'), $this->view)
            ->andReturn(Result::void());

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldReceive('run')->once()
            ->with(Mockery::type(ListContainerSummariesDQL::class))
            ->andReturn(Result::success($this->view));

        $useCase = new ListContainerSummariesUseCase($queries, $views, $this->events,
            $this->registrar);

        $result = $useCase->execute($this->query);

        expect($result->isSuccess())->toBeTrue()
            ->and($this->events->captured(MetaEvent::ViewCacheHit))->toBeFalse();
    });

    it('keys the cache by the requested container', function () {
        $keys = [];

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('get')->twice()
            ->andReturnUsing(function (ViewCacheGroup $group, string $key) use (&$keys) {
                $keys[] = $key;

                return Result::failure(anError(404, 'miss'));
            });
        $views->shouldReceive('put')->twice()->andReturn(Result::void());

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldReceive('run')->twice()->andReturn(Result::success($this->view));

        $useCase = new ListContainerSummariesUseCase($queries, $views, $this->events,
            $this->registrar);

        $useCase->execute(new ListContainerSummariesQuery(caller('container:summary'), id: 'C1'));
        $useCase->execute(new ListContainerSummariesQuery(caller('container:summary'), id: 'C2'));

        expect($keys[0])->not->toBe($keys[1]);
    });

    it('never caches a failed query', function () {
        $broken = anError(500, 'connection lost');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('get')->once()->andReturn(Result::failure(anError(404, 'miss')));
        $views->shouldNotReceive('put');

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldReceive('run')->once()->andReturn(Result::failure($broken));

        $useCase = new ListContainerSummariesUseCase($queries, $views, $this->events,
            $this->registrar);

        $result = $useCase->execute($this->query);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($broken);
    });

    it('refuses a caller without the permission before reading the cache', function () {
        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('get');

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldNotReceive('run');

        $useCase = new ListContainerSummariesUseCase($queries, $views, $this->events,
            $this->registrar);

        $result = $useCase->execute(new ListContainerSummariesQuery(stranger()));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(403);
    });
})->group('App', 'UseCase');
