<?php

declare(strict_types=1);

use Domain\ID\IDatabaseIdGenerator;
use Domain\Models\Internal\User;
use Domain\Models\IUser;
use Domain\Security\ISecureHasher;
use Domain\TableModules\Interno\UserTM;
use Shared\Exceptions\Leaf;

describe('UserTM', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();
        $ids = Mockery::mock(IDatabaseIdGenerator::class);
        $ids->shouldReceive('generate')->andReturn('USER1');
        $hasher = Mockery::mock(ISecureHasher::class);
        $hasher->shouldReceive('hash')->andReturnUsing(fn (string $p): string => 'hash:' . $p);
        $this->tm = new UserTM($ids, $hasher);
    });

    it('creates a user, hashing the password', function () {
        $result = $this->tm->create('Allan', 'allan@example.com', 'Str0ngPass', []);

        expect($result->isSuccess())->toBeTrue();

        /** @var IUser $user */
        $user = $result->getValue();

        expect($user->id)->toBe('USER1')
            ->and($user->email)->toBe('allan@example.com')
            ->and($user->passwordHash)->toBe('hash:Str0ngPass');
    });

    it('rejects an invalid email format with 422', function () {
        $result = $this->tm->create('Allan', 'not-an-email', 'Str0ngPass', []);

        expect($result->isSuccess())->toBeFalse()
            ->and(Leaf::getError($result->getErrorId())?->code)->toBe(422)
            ->and(Leaf::getError($result->getErrorId())?->details?->hasKey('email'))->toBeTrue();
    });

    it('rejects a weak password', function (string $password) {
        $result = $this->tm->create('Allan', 'allan@example.com', $password, []);

        expect($result->isSuccess())->toBeFalse()
            ->and(Leaf::getError($result->getErrorId())?->details?->hasKey('password'))->toBeTrue();
    })->with([
        'too short' => 'Ab1',
        'no uppercase' => 'abcd1234',
        'no digit' => 'AbcdEfgh',
    ]);

    it('collects name, email and password errors together', function () {
        $result = $this->tm->create('', 'bad', 'weak', []);

        expect(Leaf::getError($result->getErrorId())?->details?->count())->toBe(3);
    });

    it('updates the profile without touching password or roles', function () {
        $existing = new User('U9', 'Old', 'old@example.com', 'hash:keep', ['R1']);

        $result = $this->tm->update($existing, 'New', 'new@example.com');

        expect($result->isSuccess())->toBeTrue();
        $user = $result->getValue();

        expect($user->id)->toBe('U9')
            ->and($user->name)->toBe('New')
            ->and($user->email)->toBe('new@example.com')
            ->and($user->passwordHash)->toBe('hash:keep')
            ->and($user->roles)->toBe(['R1']);
    });

    it('changes the password, re-hashing and validating strength', function () {
        $existing = new User('U9', 'Old', 'old@example.com', 'hash:old', []);

        $ok = $this->tm->changePassword($existing, 'N3wStrong');
        $bad = $this->tm->changePassword($existing, 'weak');

        expect($ok->isSuccess())->toBeTrue()
            ->and($ok->getValue()->passwordHash)->toBe('hash:N3wStrong')
            ->and($bad->isSuccess())->toBeFalse()
            ->and(Leaf::getError($bad->getErrorId())?->code)->toBe(422);
    });

    it('resets the password the same way change does', function () {
        $existing = new User('U9', 'Old', 'old@example.com', 'hash:old', []);

        $result = $this->tm->resetPassword($existing, 'R3setPass');

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue()->passwordHash)->toBe('hash:R3setPass');
    });
})->group('Domain', 'TableModule');
