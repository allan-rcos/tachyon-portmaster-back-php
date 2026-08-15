<?php

declare(strict_types=1);

use App\Commands\Account\ChangePasswordCommand;
use App\Services\Interno\ChangePasswordUseCase;
use Infra\Repository\IUserRepository;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * Someone changing their own password, which is why it declares no permission:
 * the caller is the subject, and the current password is the authorisation.
 *
 * The user is built through the real
 * {@see \Domain\TableModules\Interno\UserTM}, so the stored hash is one the real
 * {@see \Domain\TableModules\Interno\AuthTM} actually verifies — a fixture hash
 * would make the re-authentication step pass or fail for reasons that have
 * nothing to do with the use case.
 *
 * There is no cache invalidation here on purpose: no view carries a password.
 */
describe('ChangePasswordUseCase transaction', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->userTM = domain()->userTM();
        $this->authTM = domain()->authTM();

        $built = $this->userTM->create('Allan', 'allan@example.com', 'Str0ngPassword', []);
        $this->user = $built->getValue();

        $this->context = caller();
        $this->command = new ChangePasswordCommand(
            context: $this->context,
            currentPassword: 'Str0ngPassword',
            newPassword: 'An0therStrongOne',
        );
    });

    it('commits a re-hashed password when the current one checks out', function () {
        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findById')->once()->with($this->context->id)
            ->andReturn(Result::success($this->user));
        $users->shouldReceive('update')->once()
            ->with(Mockery::on(fn ($u) => $u->passwordHash !== $this->user->passwordHash))
            ->andReturn(Result::void());

        $useCase = new ChangePasswordUseCase(commitsOnce(), $users, $this->authTM, $this->userTM);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue())->toBeNull();
    });

    it('rolls back with 401 when the current password is wrong', function () {
        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findById')->once()->andReturn(Result::success($this->user));
        // The whole point of the step: a stolen session must not be enough to
        // take the account over.
        $users->shouldNotReceive('update');

        $useCase = new ChangePasswordUseCase(rollsBackOnce(), $users, $this->authTM, $this->userTM);

        $result = $useCase->execute(new ChangePasswordCommand(
            context: $this->context,
            currentPassword: 'NotThePassword',
            newPassword: 'An0therStrongOne',
        ));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(401);
    });

    it('rolls back with 422 when the new password is too weak', function () {
        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findById')->once()->andReturn(Result::success($this->user));
        $users->shouldNotReceive('update');

        $useCase = new ChangePasswordUseCase(rollsBackOnce(), $users, $this->authTM, $this->userTM);

        $result = $useCase->execute(new ChangePasswordCommand(
            context: $this->context,
            currentPassword: 'Str0ngPassword',
            newPassword: 'weak',
        ));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(422);
    });

    it('rolls back when the account behind the session is gone', function () {
        $missing = anError(404, 'no such user');

        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findById')->once()->andReturn(Result::failure($missing));
        $users->shouldNotReceive('update');

        $useCase = new ChangePasswordUseCase(rollsBackOnce(), $users, $this->authTM, $this->userTM);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($missing);
    });

    it('rolls back when the write fails', function () {
        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findById')->once()->andReturn(Result::success($this->user));
        $users->shouldReceive('update')->once()->andReturn(Result::failure(anError()));

        $useCase = new ChangePasswordUseCase(rollsBackOnce(), $users, $this->authTM, $this->userTM);

        expect($useCase->execute($this->command)->isSuccess())->toBeFalse();
    });
})->group('App', 'UseCase');
