<?php

declare(strict_types=1);

use Domain\Models\IPermission;
use Domain\TableModules\Interno\PermissionTM;
use Shared\Exceptions\Leaf;

describe('PermissionTM', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();
        $this->tm = new PermissionTM();
    });

    it('builds a valid permission with no registry index yet', function () {
        $result = $this->tm->create('product:create');

        expect($result->isSuccess())->toBeTrue();

        /** @var IPermission $permission */
        $permission = $result->getValue();

        expect($permission->slug)->toBe('product:create')
            // 0 means "built but not registered": only the repository assigns it.
            ->and($permission->id)->toBe(0);
    });

    it('accepts kebab-case on both halves of the slug', function () {
        expect($this->tm->create('user:change-password')->isSuccess())->toBeTrue();
    });

    it('rejects a malformed slug with 422', function (string $slug) {
        $result = $this->tm->create($slug);

        expect($result->isSuccess())->toBeFalse();

        $context = Leaf::getError($result->getErrorId());

        expect($context)->not->toBeNull()
            ->and($context->code)->toBe(422)
            ->and($context->details?->hasKey('slug'))->toBeTrue();
    })->with([
        'no colon' => 'productcreate',
        'uppercase' => 'Product:Create',
        'spaces' => 'product create',
        'empty half' => 'product:',
        'snake_case' => 'product:create_thing',
        'empty' => '',
    ]);

    it('rejects a slug longer than the registry column', function () {
        // The registry table is ENGINE=MEMORY, where an over-long value is a
        // write error at boot rather than a silent truncation — so the ceiling
        // is enforced here, where it turns into a 422 instead.
        $result = $this->tm->create('product:'.str_repeat('a', 64));

        expect($result->isSuccess())->toBeFalse()
            ->and(Leaf::getError($result->getErrorId())?->details?->hasKey('slug'))->toBeTrue();
    });
})->group('Domain', 'TableModule');
