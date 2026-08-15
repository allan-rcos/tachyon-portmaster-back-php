<?php

declare(strict_types=1);

use App\Services\Interno\ValidateSessionUseCase;
use Domain\Models\Internal\Role;
use Domain\Models\Internal\User;
use Infra\Repository\IRoleRepository;
use Infra\Repository\IUserRepository;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * What a refresh runs before minting a new access token, and the reason a
 * revoked account stops working before its token expires.
 *
 * The roles are re-read rather than carried over from the old token: a
 * permission taken away half an hour ago has to be gone from the next token, and
 * copying the claim across would keep granting it until the refresh token itself
 * ran out.
 *
 * A deleted account answers the same opaque 401 as an invalid session — the 404
 * from the lookup is deliberately not passed through, so the endpoint cannot be
 * used to learn whether an account still exists.
 */
describe('ValidateSessionUseCase', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->context = caller('product:read');
        $this->user = new User(
            $this->context->id, 'Allan', 'allan@example.com', 'stored-hash', [],
        );
    });

    it('commits and answers the user with freshly read roles', function () {
        $roles = [new Role('R1', 'Operator', ['container:seal'])];

        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findById')->once()->with($this->context->id)
            ->andReturn(Result::success($this->user));

        $roleRepository = Mockery::mock(IRoleRepository::class);
        $roleRepository->shouldReceive('findByUserId')->once()->andReturn(Result::success($roles));

        $useCase = new ValidateSessionUseCase(commitsOnce(), $users, $roleRepository);

        $result = $useCase->execute($this->context);

        // From the repository, not from the context the caller arrived with.
        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue()->roles)->toBe($roles);
    });

    it('answers an opaque 401 when the account is gone, hiding the 404', function () {
        $missing = anError(404, 'no such user');

        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findById')->once()->andReturn(Result::failure($missing));

        $roleRepository = Mockery::mock(IRoleRepository::class);
        $roleRepository->shouldNotReceive('findByUserId');

        $useCase = new ValidateSessionUseCase(rollsBackOnce(), $users, $roleRepository);

        $result = $useCase->execute($this->context);

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(401)
            // Not the repository's own failure: passing it through would leak
            // that the address once existed.
            ->and($result->getErrorId())->not->toBe($missing);
    });

    it('rolls back and fails when the roles cannot be read', function () {
        $broken = anError(500, 'connection lost');

        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findById')->once()->andReturn(Result::success($this->user));

        $roleRepository = Mockery::mock(IRoleRepository::class);
        $roleRepository->shouldReceive('findByUserId')->once()->andReturn(Result::failure($broken));

        $useCase = new ValidateSessionUseCase(rollsBackOnce(), $users, $roleRepository);

        $result = $useCase->execute($this->context);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($broken);
    });
})->group('App', 'UseCase');
