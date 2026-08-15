<?php

declare(strict_types=1);

use App\Queries\Account\GetAccountQuery;
use App\Services\Interno\GetAccountUseCase;
use Infra\Query\Account\AccountView;
use Infra\Query\Interno\GetAccountDQL;
use Infra\Query\IQueryRepository;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * Reading one's own account: no permission, no cache, and the id comes from the
 * session.
 *
 * The 404 is the interesting case rather than a formality. Getting here means
 * the token was valid, so the row was there when it was issued — an empty
 * result means the account was deleted mid-session, and answering an empty
 * profile instead of a status would leave a client rendering a blank page it
 * cannot explain.
 */
describe('GetAccountUseCase', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->context = caller();
        $this->query = new GetAccountQuery($this->context);
    });

    it('answers the account view for the id in the session', function () {
        $view = new AccountView($this->context->id, 'Allan', 'allan@example.com', []);

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldReceive('run')->once()->with(Mockery::type(GetAccountDQL::class))
            ->andReturn(Result::success($view));

        $result = (new GetAccountUseCase($queries))->execute($this->query);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue())->toBe($view);
    });

    it('turns an empty result into a 404', function () {
        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldReceive('run')->once()->andReturn(Result::void());

        $result = (new GetAccountUseCase($queries))->execute($this->query);

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(404);
    });

    it('passes a query failure through as it is', function () {
        $broken = anError(500, 'connection lost');

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldReceive('run')->once()->andReturn(Result::failure($broken));

        $result = (new GetAccountUseCase($queries))->execute($this->query);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($broken);
    });
})->group('App', 'UseCase');
