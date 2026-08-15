<?php

declare(strict_types=1);

use Domain\Models\IMarkerGroup;
use Domain\TableModules\Interno\MarkerGroupTM;
use Shared\Exceptions\Leaf;

describe('MarkerGroupTM', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();
        $this->tm = new MarkerGroupTM();
    });

    it('builds a valid group with no registry index yet', function () {
        $result = $this->tm->create('refresh-token');

        expect($result->isSuccess())->toBeTrue();

        /** @var IMarkerGroup $group */
        $group = $result->getValue();

        expect($group->slug)->toBe('refresh-token')
            // 0 means "built but not registered": only the repository assigns it.
            ->and($group->id)->toBe(0);
    });

    it('accepts a single-word slug', function () {
        expect($this->tm->create('logout')->isSuccess())->toBeTrue();
    });

    it('rejects a malformed slug with 422', function (string $slug) {
        $result = $this->tm->create($slug);

        expect($result->isSuccess())->toBeFalse();

        $context = Leaf::getError($result->getErrorId());

        expect($context)->not->toBeNull()
            ->and($context->code)->toBe(422)
            ->and($context->details?->hasKey('slug'))->toBeTrue();
    })->with([
        'uppercase' => 'Refresh-Token',
        'snake_case' => 'refresh_token',
        'spaces' => 'refresh token',
        'colon' => 'auth:refresh',
        'leading digit' => '1refresh',
        'trailing dash' => 'refresh-',
        'double dash' => 'refresh--token',
        'empty' => '',
    ]);

    it('rejects a slug past the ceiling', function () {
        // The group is registered once per worker at WorkerStart, and a slug
        // that cannot be stored surfaces there as a failed boot. The ceiling is
        // enforced here so it is a 422 instead.
        $result = $this->tm->create(str_repeat('a', 65));

        expect($result->isSuccess())->toBeFalse()
            ->and(Leaf::getError($result->getErrorId())?->details?->hasKey('slug'))->toBeTrue();
    });

    it('accepts a slug exactly at the ceiling', function () {
        expect($this->tm->create(str_repeat('a', 64))->isSuccess())->toBeTrue();
    });
})->group('Domain', 'TableModule');
