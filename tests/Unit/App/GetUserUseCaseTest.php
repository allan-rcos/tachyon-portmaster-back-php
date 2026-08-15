<?php

declare(strict_types=1);

use App\Queries\User\GetUserQuery;
use App\Services\Interno\GetUserUseCase;
use Infra\Query\Account\AccountView;
use Infra\Query\Interno\GetAccountDQL;
use Infra\Query\IQueryRepository;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * Reading someone else's account, which is the same query
 * {@see \Tests\Unit\App\GetAccountUseCaseTest} runs against the caller's own id.
 *
 * The view is shared deliberately — one shape, one place where a field could
 * accidentally be added — and the difference is entirely in who may ask: this
 * one is behind `user:get`, the other behind nothing at all.
 */
describe('GetUserUseCase', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->registrar = registrar();
        $this->query = new GetUserQuery(caller('user:get'), 'U2');
    });

    it('answers the account view the query found', function () {
        $view = new AccountView('U2', 'Someone', 'someone@example.com', []);

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldReceive('run')->once()->with(Mockery::type(GetAccountDQL::class))
            ->andReturn(Result::success($view));

        $useCase = new GetUserUseCase($queries, $this->registrar);

        $result = $useCase->execute($this->query);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue())->toBe($view);
    });

    it('turns an empty result into a 404', function () {
        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldReceive('run')->once()->andReturn(Result::void());

        $useCase = new GetUserUseCase($queries, $this->registrar);

        $result = $useCase->execute($this->query);

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(404);
    });

    it('passes a query failure through as it is', function () {
        $broken = anError(500, 'connection lost');

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldReceive('run')->once()->andReturn(Result::failure($broken));

        $useCase = new GetUserUseCase($queries, $this->registrar);

        $result = $useCase->execute($this->query);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($broken);
    });

    it('refuses a caller without the permission before querying', function () {
        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldNotReceive('run');

        $useCase = new GetUserUseCase($queries, $this->registrar);

        $result = $useCase->execute(new GetUserQuery(stranger(), 'U2'));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(403);
    });
})->group('App', 'UseCase');
