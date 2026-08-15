<?php

declare(strict_types=1);

use App\Queries\Marker\GetMarkerQuery;
use App\Services\Interno\GetMarkerUseCase;
use Infra\Repository\IMarkerRepository;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * Reading a marker, which is what a refresh does before spending a token.
 *
 * The plaintext value never reaches the store: the table module hashes it, and
 * the lookup is by digest. That is asserted here rather than only in
 * {@see \Tests\Unit\Domain\MarkerTMTest}, because a use case that passed the raw
 * value through would put live refresh tokens in a store the sweeper walks.
 *
 * The repository's answer is returned untouched — including its 404 — because
 * what a missing marker means depends on who is asking, and that is the caller's
 * decision rather than this one's.
 */
describe('GetMarkerUseCase', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->markerTM = domain()->markerTM();
        $this->query = new GetMarkerQuery('refresh-token', 'the-token');
    });

    it('looks the marker up by its digest, never by the plaintext', function () {
        $markers = Mockery::mock(IMarkerRepository::class);
        $markers->shouldReceive('get')->once()
            ->with('refresh-token', Mockery::on(fn (string $key) => $key !== 'the-token'))
            ->andReturn(Result::success(true));

        $result = (new GetMarkerUseCase($this->markerTM, $markers))->execute($this->query);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue())->toBeTrue();
    });

    it('answers the consumed flag as it stands', function () {
        $markers = Mockery::mock(IMarkerRepository::class);
        $markers->shouldReceive('get')->once()->andReturn(Result::success(false));

        $result = (new GetMarkerUseCase($this->markerTM, $markers))->execute($this->query);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue())->toBeFalse();
    });

    it('passes the repository failure through untouched', function () {
        $absent = anError(404, 'no such marker');

        $markers = Mockery::mock(IMarkerRepository::class);
        $markers->shouldReceive('get')->once()->andReturn(Result::failure($absent));

        $result = (new GetMarkerUseCase($this->markerTM, $markers))->execute($this->query);

        // Not reinterpreted: what "no marker" means belongs to whoever asked.
        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($absent);
    });

    it('refuses with 422 before reading anything when the group is invalid', function () {
        $markers = Mockery::mock(IMarkerRepository::class);
        $markers->shouldNotReceive('get');

        $result = (new GetMarkerUseCase($this->markerTM, $markers))
            ->execute(new GetMarkerQuery('', ''));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(422);
    });
})->group('App', 'UseCase');
