<?php

declare(strict_types=1);

use App\Commands\Account\UpdateAccountCommand;
use App\Services\Interno\UpdateAccountUseCase;
use Domain\Models\Internal\User;
use Infra\Repository\IUserRepository;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * Editing one's own profile, and the id comes from the session rather than the
 * command.
 *
 * That is the whole security model of this use case: with no id to supply,
 * there is nothing to tamper with, and no permission is needed because the
 * caller can only ever be editing themselves. It is asserted here — the lookup
 * must use the context's id — because a refactor that added an id to the command
 * would turn a permission-free endpoint into one that edits anybody.
 */
describe('UpdateAccountUseCase transaction', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->userTM = domain()->userTM();
        $this->context = caller();
        $this->existing = new User(
            $this->context->id, 'Allan', 'allan@example.com', 'stored-hash', [],
        );
        $this->command = new UpdateAccountCommand(
            $this->context, 'Allan Costa', 'allan.costa@example.com',
        );
    });

    it('commits against the id in the session, keeping the password hash', function () {
        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findById')->once()->with($this->context->id)
            ->andReturn(Result::success($this->existing));
        $users->shouldReceive('update')->once()->andReturn(Result::void());

        $useCase = new UpdateAccountUseCase(commitsOnce(), $users, $this->userTM);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue()->name)->toBe('Allan Costa')
            ->and($result->getValue()->passwordHash)->toBe('stored-hash');
    });

    it('rolls back when the account behind the session is gone', function () {
        $missing = anError(404, 'no such user');

        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findById')->once()->andReturn(Result::failure($missing));
        $users->shouldNotReceive('update');

        $useCase = new UpdateAccountUseCase(rollsBackOnce(), $users, $this->userTM);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($missing);
    });

    it('rolls back with 422 when the new e-mail is malformed', function () {
        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findById')->once()->andReturn(Result::success($this->existing));
        $users->shouldNotReceive('update');

        $useCase = new UpdateAccountUseCase(rollsBackOnce(), $users, $this->userTM);

        $result = $useCase->execute(new UpdateAccountCommand(
            $this->context, 'Allan Costa', 'not-an-email',
        ));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(422);
    });

    it('rolls back when the write fails', function () {
        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findById')->once()->andReturn(Result::success($this->existing));
        $users->shouldReceive('update')->once()->andReturn(Result::failure(anError()));

        $useCase = new UpdateAccountUseCase(rollsBackOnce(), $users, $this->userTM);

        expect($useCase->execute($this->command)->isSuccess())->toBeFalse();
    });
})->group('App', 'UseCase');
