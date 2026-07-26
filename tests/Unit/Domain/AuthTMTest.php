<?php

declare(strict_types=1);

use Domain\Models\Internal\User;
use Domain\Security\ISecureHasher;
use Domain\TableModules\Interno\AuthTM;
use Shared\Exceptions\Leaf;

describe('AuthTM', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();
        $this->user = new User('U1', 'Allan', 'allan@example.com', 'stored-hash', []);
    });

    it('passes login when the hasher verifies the password', function () {
        $hasher = Mockery::mock(ISecureHasher::class);
        $hasher->shouldReceive('verify')->once()->with('secret', 'stored-hash')->andReturn(true);

        $result = (new AuthTM($hasher))->login($this->user, 'secret');

        expect($result->isSuccess())->toBeTrue();
    });

    it('fails login with 401 when verification fails', function () {
        $hasher = Mockery::mock(ISecureHasher::class);
        $hasher->shouldReceive('verify')->once()->andReturn(false);

        $result = (new AuthTM($hasher))->login($this->user, 'wrong');

        expect($result->isSuccess())->toBeFalse()
            ->and(Leaf::getError($result->getErrorId())?->code)->toBe(401);
    });

    it('does not reveal whether the email or the password was wrong', function () {
        $hasher = Mockery::mock(ISecureHasher::class);
        $hasher->shouldReceive('verify')->andReturn(false);

        $result = (new AuthTM($hasher))->login($this->user, 'wrong');

        expect(Leaf::getError($result->getErrorId())?->message)->toBe('Invalid e-mail or password.');
    });
})->group('Domain', 'TableModule');
