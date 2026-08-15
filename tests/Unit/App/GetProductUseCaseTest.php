<?php

declare(strict_types=1);

use App\Queries\Product\GetProductQuery;
use App\Services\Interno\GetProductUseCase;
use Domain\Enums\RiskClass;
use Infra\Query\Interno\GetProductDQL;
use Infra\Query\IQueryRepository;
use Infra\Query\Product\ProductViewItem;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * A read by id opens no transaction, so what it has to get right is the
 * difference between "the query failed" and "the query worked and found
 * nothing".
 *
 * The second is a 404 the use case raises itself: the query answers a success
 * carrying nothing, because an empty result set is not a database error, and
 * turning that into a status is an application decision. A read by id is also
 * deliberately not cached — it is a point lookup, and a stale one would be read
 * straight after the write that changed it.
 */
describe('GetProductUseCase', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->registrar = registrar();
        $this->query = new GetProductQuery(caller('product:read'), 'PROD1');
    });

    it('answers the view item the query found', function () {
        $item = new ProductViewItem('PROD1', 'Diesel', 0.85, RiskClass::Class3FlammableLiquids);

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldReceive('run')->once()->with(Mockery::type(GetProductDQL::class))
            ->andReturn(Result::success($item));

        $useCase = new GetProductUseCase($queries, $this->registrar);

        $result = $useCase->execute($this->query);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue())->toBe($item);
    });

    it('turns an empty result into a 404', function () {
        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldReceive('run')->once()->andReturn(Result::void());

        $useCase = new GetProductUseCase($queries, $this->registrar);

        $result = $useCase->execute($this->query);

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(404);
    });

    it('passes a query failure through as it is', function () {
        $broken = anError(500, 'connection lost');

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldReceive('run')->once()->andReturn(Result::failure($broken));

        $useCase = new GetProductUseCase($queries, $this->registrar);

        $result = $useCase->execute($this->query);

        // Not rewritten to a 404: the row may well exist, and reporting it as
        // missing would send a client away from data that is only unreachable.
        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($broken);
    });

    it('refuses a caller without the permission before querying', function () {
        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldNotReceive('run');

        $useCase = new GetProductUseCase($queries, $this->registrar);

        $result = $useCase->execute(new GetProductQuery(stranger(), 'PROD1'));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(403);
    });
})->group('App', 'UseCase');
