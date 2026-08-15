<?php

declare(strict_types=1);

use App\Events\MetaEvent;
use App\Queries\Product\ListProductsQuery;
use App\Services\Interno\ListProductsUseCase;
use Domain\Enums\RiskClass;
use Ds\Seq;
use Infra\Query\Interno\ListProductsDQL;
use Infra\Query\IQueryRepository;
use Infra\Query\Product\ProductListView;
use Infra\Query\Product\ProductViewItem;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * The model for every cached listing: hit short-circuits, miss queries and
 * stores, and a failed query stores nothing.
 *
 * The last one is what keeps a database outage from being served back as an
 * empty page for the whole TTL. The hit is also the only path that emits
 * {@see MetaEvent::ViewCacheHit}, which is what the `Cache-Status` header is
 * built from, so asserting it here is asserting the header.
 */
describe('ListProductsUseCase caching', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->registrar = registrar();
        $this->events = events();
        $this->query = new ListProductsQuery(caller('product:read'), limit: 10);
        $this->view = new ProductListView(
            items: new Seq([
                new ProductViewItem('PROD1', 'Diesel', 0.85, RiskClass::Class3FlammableLiquids),
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

        $useCase = new ListProductsUseCase($queries, $views, $this->events, $this->registrar);

        $result = $useCase->execute($this->query);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue())->toBe($this->view)
            ->and($this->events->captured(MetaEvent::ViewCacheHit))->toBeTrue();
    });

    it('queries and stores on a miss, and reports no hit', function () {
        $views = Mockery::mock(IViewCacheRepository::class);
        // A miss is a failure carrying nothing — the use case reads the value,
        // which is null, and falls through.
        $views->shouldReceive('get')->once()->andReturn(Result::failure(anError(404, 'miss')));
        $views->shouldReceive('put')->once()
            ->with(ViewCacheGroup::Product, Mockery::type('string'), $this->view)
            ->andReturn(Result::void());

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldReceive('run')->once()->with(Mockery::type(ListProductsDQL::class))
            ->andReturn(Result::success($this->view));

        $useCase = new ListProductsUseCase($queries, $views, $this->events, $this->registrar);

        $result = $useCase->execute($this->query);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue())->toBe($this->view)
            ->and($this->events->captured(MetaEvent::ViewCacheHit))->toBeFalse();
    });

    it('never caches a failed query', function () {
        $broken = anError(500, 'connection lost');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('get')->once()->andReturn(Result::failure(anError(404, 'miss')));
        // Storing here would serve the outage as an empty page for a whole TTL.
        $views->shouldNotReceive('put');

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldReceive('run')->once()->andReturn(Result::failure($broken));

        $useCase = new ListProductsUseCase($queries, $views, $this->events, $this->registrar);

        $result = $useCase->execute($this->query);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($broken);
    });

    it('refuses a caller without the permission before reading the cache', function () {
        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('get');
        $views->shouldNotReceive('put');

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldNotReceive('run');

        $useCase = new ListProductsUseCase($queries, $views, $this->events, $this->registrar);

        $result = $useCase->execute(new ListProductsQuery(stranger()));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(403);
    });
})->group('App', 'UseCase');
