<?php

declare(strict_types=1);

use Domain\ID\IDatabaseIdGenerator;
use Domain\Models\Internal\Role;
use Domain\Models\IRole;
use Domain\TableModules\Interno\RoleTM;
use Shared\Exceptions\Leaf;

describe('RoleTM', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();
        $ids = Mockery::mock(IDatabaseIdGenerator::class);
        $ids->shouldReceive('generate')->andReturn('ROLE1');
        $this->tm = new RoleTM($ids);
    });

    it('creates a role carrying its permission slugs', function () {
        $result = $this->tm->create('Operator', ['container:seal', 'container:dispatch']);

        expect($result->isSuccess())->toBeTrue();

        /** @var IRole $role */
        $role = $result->getValue();

        expect($role->id)->toBe('ROLE1')
            ->and($role->name)->toBe('Operator')
            ->and($role->permissions)->toBe(['container:seal', 'container:dispatch']);
    });

    it('rejects a blank name with 422', function () {
        $result = $this->tm->create('', ['product:read']);

        expect($result->isSuccess())->toBeFalse()
            ->and(Leaf::getError($result->getErrorId())?->code)->toBe(422)
            ->and(Leaf::getError($result->getErrorId())?->details?->hasKey('name'))->toBeTrue();
    });

    it('rejects a name past 255 characters', function () {
        $result = $this->tm->create(str_repeat('a', 256), []);

        expect(Leaf::getError($result->getErrorId())?->details?->hasKey('name'))->toBeTrue();
    });

    it('replaces the permission set on update, keeping id and name', function () {
        $existing = new Role('R7', 'Reader', ['product:read']);

        $result = $this->tm->updatePermissions($existing, ['product:read', 'metrics:read']);

        expect($result->isSuccess())->toBeTrue();
        $role = $result->getValue();

        expect($role->id)->toBe('R7')
            ->and($role->name)->toBe('Reader')
            ->and($role->permissions)->toBe(['product:read', 'metrics:read']);
    });

    it('allows creating a role with no permissions', function () {
        $result = $this->tm->create('Empty', []);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue()->permissions)->toBe([]);
    });
})->group('Domain', 'TableModule');
