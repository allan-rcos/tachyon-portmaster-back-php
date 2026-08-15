<?php

declare(strict_types=1);

use App\Commands\LoginCommand;
use App\Services\Interno\LoginUseCase;
use Domain\Models\Internal\Role;
use Infra\Repository\IRoleRepository;
use Infra\Repository\IUserRepository;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * The front door, and the one use case whose failures are deliberately
 * uninformative.
 *
 * An unknown address and a wrong password answer the same 401 with the same
 * message, because any difference between them — including a difference only
 * visible in the error text — turns this endpoint into a way to enumerate who
 * holds an account here. That equivalence is asserted directly, comparing the
 * two failures rather than checking each is "some 401".
 *
 * The roles are loaded here rather than left to the session, because they are
 * what goes into the token: a login that answered before reading them would mint
 * a token granting nothing.
 */
describe('LoginUseCase', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->userTM = domain()->userTM();
        $this->authTM = domain()->authTM();

        $this->user = $this->userTM
            ->create('Allan', 'allan@example.com', 'Str0ngPassword', [])
            ->getValue();

        $this->command = new LoginCommand('allan@example.com', 'Str0ngPassword');
    });

    it('commits and answers the user carrying the roles it loaded', function () {
        $roles = [new Role('R1', 'Operator', ['product:read'])];

        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findByEmail')->once()->with('allan@example.com')
            ->andReturn(Result::success($this->user));

        $roleRepository = Mockery::mock(IRoleRepository::class);
        $roleRepository->shouldReceive('findByUserId')->once()->with($this->user->id)
            ->andReturn(Result::success($roles));

        $useCase = new LoginUseCase(commitsOnce(), $users, $roleRepository, $this->authTM);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue()->email)->toBe('allan@example.com')
            ->and($result->getValue()->roles)->toBe($roles);
    });

    it('answers an unknown address and a wrong password identically', function () {
        $absent = Mockery::mock(IUserRepository::class);
        $absent->shouldReceive('findByEmail')->once()
            ->andReturn(Result::failure(anError(404, 'no such user')));

        $present = Mockery::mock(IUserRepository::class);
        $present->shouldReceive('findByEmail')->once()->andReturn(Result::success($this->user));

        // Neither path may reach the roles: both stop at the credentials.
        $roles = Mockery::mock(IRoleRepository::class);
        $roles->shouldNotReceive('findByUserId');

        $unknownEmail = (new LoginUseCase(rollsBackOnce(), $absent, $roles, $this->authTM))
            ->execute(new LoginCommand('nobody@example.com', 'Str0ngPassword'));

        $wrongPassword = (new LoginUseCase(rollsBackOnce(), $present, $roles, $this->authTM))
            ->execute(new LoginCommand('allan@example.com', 'NotThePassword'));

        // Same status and same message: nothing here tells a caller which half
        // of the pair was wrong.
        expect(codeOf($unknownEmail))->toBe(401)
            ->and(codeOf($wrongPassword))->toBe(401)
            ->and(Leaf::getError($unknownEmail->getErrorId())?->message)
            ->toBe(Leaf::getError($wrongPassword->getErrorId())?->message);
    });

    it('rolls back and answers no session when the roles cannot be read', function () {
        $broken = anError(500, 'connection lost');

        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findByEmail')->once()->andReturn(Result::success($this->user));

        $roleRepository = Mockery::mock(IRoleRepository::class);
        $roleRepository->shouldReceive('findByUserId')->once()->andReturn(Result::failure($broken));

        $useCase = new LoginUseCase(rollsBackOnce(), $users, $roleRepository, $this->authTM);

        $result = $useCase->execute($this->command);

        // Failing outright rather than issuing a token with an empty role list,
        // which would read to the client as an account that lost its access.
        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($broken);
    });
})->group('App', 'UseCase');
