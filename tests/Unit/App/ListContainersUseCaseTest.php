<?php

declare(strict_types=1);

use App\Events\MetaEvent;
use App\Queries\Container\ListContainersQuery;
use App\Services\Interno\ListContainersUseCase;
use Domain\Enums\ContainerStatus;
use Ds\Seq;
use Infra\Query\Container\ContainerListView;
use Infra\Query\Container\ContainerViewItem;
use Infra\Query\Interno\ListContainersDQL;
use Infra\Query\IQueryRepository;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * The cached-listing shape, with the filters this endpoint adds.
 *
 * The cache key comes from the query object, so two different filters must not
 * share an entry — that is asserted here rather than left to the DQL's own test,
 * because a collision would serve one caller's filtered page to another and
 * nothing else in the stack would notice.
 */
describe('ListContainersUseCase caching', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->registrar = registrar();
        $this->events = events();
        $this->query = new ListContainersQuery(caller('container:read'), limit: 10);
        $this->view = new ContainerListView(
            items: new Seq([
                new ContainerViewItem('C1', 'BOX-1', 30.0, 100.0, ContainerStatus::Loading),
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

        $useCase = new ListContainersUseCase($queries, $views, $this->events, $this->registrar);

        $result = $useCase->execute($this->query);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue())->toBe($this->view)
            ->and($this->events->captured(MetaEvent::ViewCacheHit))->toBeTrue();
    });

    it('queries and stores on a miss, and reports no hit', function () {
        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('get')->once()->andReturn(Result::failure(anError(404, 'miss')));
        $views->shouldReceive('put')->once()
            ->with(ViewCacheGroup::Container, Mockery::type('string'), $this->view)
            ->andReturn(Result::void());

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldReceive('run')->once()->with(Mockery::type(ListContainersDQL::class))
            ->andReturn(Result::success($this->view));

        $useCase = new ListContainersUseCase($queries, $views, $this->events, $this->registrar);

        $result = $useCase->execute($this->query);

        expect($result->isSuccess())->toBeTrue()
            ->and($this->events->captured(MetaEvent::ViewCacheHit))->toBeFalse();
    });

    it('keys the cache by the filters, not just by the endpoint', function () {
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

        $useCase = new ListContainersUseCase($queries, $views, $this->events, $this->registrar);

        $useCase->execute(new ListContainersQuery(caller('container:read'), status: 'sealed'));
        $useCase->execute(new ListContainersQuery(caller('container:read'), status: 'empty'));

        expect($keys[0])->not->toBe($keys[1]);
    });

    it('never caches a failed query', function () {
        $broken = anError(500, 'connection lost');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('get')->once()->andReturn(Result::failure(anError(404, 'miss')));
        // Storing here would serve the outage as an empty page for a whole TTL.
        $views->shouldNotReceive('put');

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldReceive('run')->once()->andReturn(Result::failure($broken));

        $useCase = new ListContainersUseCase($queries, $views, $this->events, $this->registrar);

        $result = $useCase->execute($this->query);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($broken);
    });

    it('refuses a caller without the permission before reading the cache', function () {
        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('get');

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldNotReceive('run');

        $useCase = new ListContainersUseCase($queries, $views, $this->events, $this->registrar);

        $result = $useCase->execute(new ListContainersQuery(stranger()));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(403);
    });
})->group('App', 'UseCase');
