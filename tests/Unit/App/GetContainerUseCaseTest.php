<?php

declare(strict_types=1);

use App\Queries\Container\GetContainerQuery;
use App\Services\Interno\GetContainerUseCase;
use Domain\Enums\ContainerStatus;
use Infra\Query\Container\ContainerViewItem;
use Infra\Query\Interno\GetContainerDQL;
use Infra\Query\IQueryRepository;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * The read-by-id shape: the query answering nothing is a 404 the use case
 * raises, and a query that failed is passed through as it is.
 *
 * Conflating the two would send a client away from a container that exists and
 * is only unreachable.
 */
describe('GetContainerUseCase', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->registrar = registrar();
        $this->query = new GetContainerQuery(caller('container:read'), 'C1');
    });

    it('answers the view item the query found', function () {
        $item = new ContainerViewItem('C1', 'BOX-1', 30.0, 100.0, ContainerStatus::Loading);

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldReceive('run')->once()->with(Mockery::type(GetContainerDQL::class))
            ->andReturn(Result::success($item));

        $useCase = new GetContainerUseCase($queries, $this->registrar);

        $result = $useCase->execute($this->query);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue())->toBe($item);
    });

    it('turns an empty result into a 404', function () {
        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldReceive('run')->once()->andReturn(Result::void());

        $useCase = new GetContainerUseCase($queries, $this->registrar);

        $result = $useCase->execute($this->query);

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(404);
    });

    it('passes a query failure through as it is', function () {
        $broken = anError(500, 'connection lost');

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldReceive('run')->once()->andReturn(Result::failure($broken));

        $useCase = new GetContainerUseCase($queries, $this->registrar);

        $result = $useCase->execute($this->query);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($broken);
    });

    it('refuses a caller without the permission before querying', function () {
        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldNotReceive('run');

        $useCase = new GetContainerUseCase($queries, $this->registrar);

        $result = $useCase->execute(new GetContainerQuery(stranger(), 'C1'));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(403);
    });
})->group('App', 'UseCase');
