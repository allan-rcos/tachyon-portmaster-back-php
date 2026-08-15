<?php

declare(strict_types=1);

use App\Commands\Marker\RegisterMarkerGroupCommand;
use App\Services\Interno\RegisterMarkerGroupUseCase;
use Domain\Models\Internal\MarkerGroup;
use Infra\Repository\IMarkerGroupRepository;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * The marker catalogue's half of the boot-time registration, and it throws for
 * the same reason
 * {@see \Tests\Unit\App\RegisterPermissionUseCaseTest} describes: this runs
 * before any request exists, so there is nobody to answer a `Result` to.
 *
 * A worker that came up without its groups would accept a marker under a group
 * it does not know, and revocation would quietly stop meaning anything.
 */
describe('RegisterMarkerGroupUseCase', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->markerGroupTM = domain()->markerGroupTM();
    });

    it('registers a group and answers its slug', function () {
        $groups = Mockery::mock(IMarkerGroupRepository::class);
        $groups->shouldReceive('add')->once()
            ->andReturn(Result::success(new MarkerGroup('refresh-token')));

        $useCase = new RegisterMarkerGroupUseCase($this->markerGroupTM, $groups);

        expect($useCase->execute(new RegisterMarkerGroupCommand('refresh-token')))
            ->toBe('refresh-token');
    });

    it('refuses to boot on a malformed slug, naming it in the message', function () {
        $groups = Mockery::mock(IMarkerGroupRepository::class);
        $groups->shouldNotReceive('add');

        $useCase = new RegisterMarkerGroupUseCase($this->markerGroupTM, $groups);

        expect(fn () => $useCase->execute(new RegisterMarkerGroupCommand('Refresh Token')))
            ->toThrow(RuntimeException::class, 'Refresh Token');
    });

    it('refuses to boot when the registry cannot be written', function () {
        $groups = Mockery::mock(IMarkerGroupRepository::class);
        $groups->shouldReceive('add')->once()
            ->andReturn(Result::failure(anError(500, 'the cache is unreachable')));

        $useCase = new RegisterMarkerGroupUseCase($this->markerGroupTM, $groups);

        expect(fn () => $useCase->execute(new RegisterMarkerGroupCommand('refresh-token')))
            ->toThrow(RuntimeException::class, 'the cache is unreachable');
    });
})->group('App', 'UseCase');
