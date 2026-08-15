<?php

declare(strict_types=1);

use App\Commands\Permission\RegisterPermissionCommand;
use App\Services\Interno\RegisterPermissionUseCase;
use Domain\TableModules\Interno\PermissionTM;
use Infra\Repository\IPermissionRepository;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;
use Tests\Doubles\InMemoryPermissionRepository;

/**
 * The one use case that throws instead of answering a `Result`, and the one that
 * is allowed to.
 *
 * It runs at `WorkerStart`, from the constructors of every guarded use case,
 * before a single request exists — there is no caller to answer, and a worker
 * whose catalogue is incomplete is a worker that will 403 requests that should
 * have been allowed. Failing to boot is the correct outcome, and the exception
 * message carries the slug because that log line is the only thing anyone will
 * have to go on.
 *
 * It is also idempotent by design: four workers run identical code and register
 * identical slugs, so a repeat has to answer the existing registration rather
 * than a conflict.
 */
describe('RegisterPermissionUseCase', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->registry = new InMemoryPermissionRepository();
        $this->useCase = new RegisterPermissionUseCase(new PermissionTM(), $this->registry);
    });

    it('registers a slug and answers it back', function () {
        $slug = $this->useCase->execute(new RegisterPermissionCommand('product:read'));

        expect($slug)->toBe('product:read')
            ->and($this->registry->getBySlug('product:read'))->not->toBeNull();
    });

    it('is idempotent, so every worker may register the same slug', function () {
        $this->useCase->execute(new RegisterPermissionCommand('product:read'));
        $second = $this->useCase->execute(new RegisterPermissionCommand('product:read'));

        // The same id, not a second row: the catalogue is a function of the
        // code, and all four copies have to agree.
        expect($second)->toBe('product:read')
            ->and($this->registry->all()->count())->toBe(1);
    });

    it('refuses to boot on a malformed slug, naming it in the message', function () {
        expect(fn () => $this->useCase->execute(new RegisterPermissionCommand('Product Read')))
            ->toThrow(RuntimeException::class, 'Product Read');
    });

    it('refuses to boot when the catalogue cannot be written', function () {
        $broken = Mockery::mock(IPermissionRepository::class);
        $broken->shouldReceive('add')->once()
            ->andReturn(Result::failure(anError(500, 'the cache is unreachable')));

        $useCase = new RegisterPermissionUseCase(new PermissionTM(), $broken);

        // Coming up anyway would leave this worker refusing requests the other
        // three allow, which is far harder to diagnose than a failed boot.
        expect(fn () => $useCase->execute(new RegisterPermissionCommand('product:read')))
            ->toThrow(RuntimeException::class, 'the cache is unreachable');
    });
})->group('App', 'UseCase');
