<?php

declare(strict_types=1);

use Domain\Models\IMarker;
use Domain\Security\Interno\XxHasher;
use Domain\TableModules\Interno\MarkerTM;
use Shared\Exceptions\Leaf;

describe('MarkerTM', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();
        $this->tm = new MarkerTM(new XxHasher());
    });

    it('hashes the value and never carries the plaintext', function () {
        $result = $this->tm->create('refresh-token', 'the-secret-token', true);

        expect($result->isSuccess())->toBeTrue();

        /** @var IMarker $marker */
        $marker = $result->getValue();

        // The only place the plaintext exists is the argument: what leaves here
        // is the digest, and a live refresh token must not be readable out of
        // the store it is filed in.
        expect($marker->group)->toBe('refresh-token')
            ->and($marker->key)->not->toBe('the-secret-token')
            ->and($marker->flag)->toBeTrue();
    });

    it('derives the same key from the same value, so a marker can be found again', function () {
        $first = $this->tm->create('refresh-token', 'the-secret-token', true)->getValue();
        $second = $this->tm->create('refresh-token', 'the-secret-token', false)->getValue();

        // Consuming a marker has to reach the entry raising it created; a hash
        // that varied per call would make every revocation a no-op.
        expect($second->key)->toBe($first->key);
    });

    it('derives different keys from different values', function () {
        $one = $this->tm->create('refresh-token', 'token-a', true)->getValue();
        $other = $this->tm->create('refresh-token', 'token-b', true)->getValue();

        expect($other->key)->not->toBe($one->key);
    });

    it('rejects a malformed group with 422', function (string $group) {
        $result = $this->tm->create($group, 'the-secret-token', true);

        expect($result->isSuccess())->toBeFalse();

        $context = Leaf::getError($result->getErrorId());

        expect($context)->not->toBeNull()
            ->and($context->code)->toBe(422)
            ->and($context->details?->hasKey('group'))->toBeTrue();
    })->with([
        'uppercase' => 'Refresh-Token',
        'snake_case' => 'refresh_token',
        'spaces' => 'refresh token',
        'leading digit' => '1refresh',
        'trailing dash' => 'refresh-',
        'empty' => '',
    ]);

    it('rejects an empty value with 422', function () {
        // An empty value hashes to a constant, so every caller passing nothing
        // would share one marker and each would see the others' flag flipping.
        $result = $this->tm->create('refresh-token', '', true);

        expect($result->isSuccess())->toBeFalse()
            ->and(Leaf::getError($result->getErrorId())?->details?->hasKey('value'))->toBeTrue();
    });

    it('reports the group and the value together rather than the first', function () {
        $result = $this->tm->create('Bad Group', '', true);
        $details = Leaf::getError($result->getErrorId())?->details;

        expect($details?->hasKey('group'))->toBeTrue()
            ->and($details?->hasKey('value'))->toBeTrue();
    });
})->group('Domain', 'TableModule');
