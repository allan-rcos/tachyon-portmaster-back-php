<?php

declare(strict_types=1);

use App\Commands\Marker\SetMarkerCommand;
use App\Services\Interno\SetMarkerUseCase;
use Infra\Repository\IMarkerRepository;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * The mechanism behind refresh-token revocation, and the one place in the
 * application where a missing record is an answer rather than a problem.
 *
 * The store is asked for the current flag before anything is written. Three
 * states come back and each means something different: no marker at all is
 * "never issued", `true` is live, `false` is already consumed. Raising one is
 * only legal from the first — an existing marker means either a duplicate issue
 * or a replay, and both are a 409. Consuming is idempotent and silent, so
 * "never issued" and "already consumed" stay indistinguishable to whoever is
 * trying tokens.
 *
 * The failure that is *not* a 404 is the load-bearing branch: raising a marker
 * against a store that could not be read is exactly how a replay gets through,
 * so it stops instead.
 */
describe('SetMarkerUseCase', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->markerTM = domain()->markerTM();
        $this->raise = new SetMarkerCommand('refresh-token', 'the-token', true, 3600);
        $this->consume = new SetMarkerCommand('refresh-token', 'the-token', false, 3600);
    });

    it('raises a marker for a value with no history', function () {
        $markers = Mockery::mock(IMarkerRepository::class);
        // The 404 is the expected answer here: nothing was ever filed.
        $markers->shouldReceive('get')->once()->andReturn(Result::failure(anError(404, 'absent')));
        $markers->shouldReceive('set')->once()
            ->with(Mockery::on(fn ($marker) => $marker->flag === true), 3600)
            ->andReturn(Result::void());

        $result = (new SetMarkerUseCase($this->markerTM, $markers))->execute($this->raise);

        expect($result->isSuccess())->toBeTrue();
    });

    it('refuses with 409 to raise a marker that is already live', function () {
        $markers = Mockery::mock(IMarkerRepository::class);
        $markers->shouldReceive('get')->once()->andReturn(Result::success(true));
        $markers->shouldNotReceive('set');

        $result = (new SetMarkerUseCase($this->markerTM, $markers))->execute($this->raise);

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(409);
    });

    it('refuses with 409 to reactivate a consumed marker', function () {
        $markers = Mockery::mock(IMarkerRepository::class);
        $markers->shouldReceive('get')->once()->andReturn(Result::success(false));
        $markers->shouldNotReceive('set');

        $result = (new SetMarkerUseCase($this->markerTM, $markers))->execute($this->raise);

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(409);
    });

    it('consumes a live marker', function () {
        $markers = Mockery::mock(IMarkerRepository::class);
        $markers->shouldReceive('get')->once()->andReturn(Result::success(true));
        $markers->shouldReceive('set')->once()
            ->with(Mockery::on(fn ($marker) => $marker->flag === false), 3600)
            ->andReturn(Result::void());

        $result = (new SetMarkerUseCase($this->markerTM, $markers))->execute($this->consume);

        expect($result->isSuccess())->toBeTrue();
    });

    it('stays silent when consuming something that was never live', function () {
        $markers = Mockery::mock(IMarkerRepository::class);
        $markers->shouldReceive('get')->once()->andReturn(Result::failure(anError(404, 'absent')));
        // Nothing to write, and nothing said about why: this is what keeps
        // "never issued" and "already consumed" indistinguishable.
        $markers->shouldNotReceive('set');

        $result = (new SetMarkerUseCase($this->markerTM, $markers))->execute($this->consume);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue())->toBeNull();
    });

    it('stops on a lookup failure that is not a 404, rather than raising', function () {
        $broken = anError(500, 'the cache is unreachable');

        $markers = Mockery::mock(IMarkerRepository::class);
        $markers->shouldReceive('get')->once()->andReturn(Result::failure($broken));
        // Treating this as "no history" would let a replayed token be marked
        // live again the moment the store came back.
        $markers->shouldNotReceive('set');

        $result = (new SetMarkerUseCase($this->markerTM, $markers))->execute($this->raise);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($broken);
    });

    it('refuses with 422 before reading anything when the group is invalid', function () {
        $markers = Mockery::mock(IMarkerRepository::class);
        $markers->shouldNotReceive('get');
        $markers->shouldNotReceive('set');

        $result = (new SetMarkerUseCase($this->markerTM, $markers))
            ->execute(new SetMarkerCommand('', '', true, 3600));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(422);
    });
})->group('App', 'UseCase');
