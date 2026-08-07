<?php

declare(strict_types=1);

use API\Auth\Interno\FirebaseJwtTokenService;
use API\Config\JwtConfig;
use API\Negociation\DTO\Token\TokenRoleX;
use API\Negociation\DTO\Token\TokenUserX;
use API\Negociation\DTO\Token\TokenUserXFactory;
use App\Context\UserContext;
use Domain\ID\Interno\NanoIdGenerator;
use Domain\Models\Internal\Role;
use Domain\Models\Internal\User;
use Firebase\JWT\JWT;
use Shared\Exceptions\Leaf;

describe('FlatBuffers token claims', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();
        // 32 bytes minimum: HS256 refuses to sign with a key shorter than its
        // own digest, so even a throwaway test secret has to clear the bar.
        $this->config = new JwtConfig(secret: 'test-secret-please-ignore-32-byte', ttlSeconds: 3600);
        $this->service = new FirebaseJwtTokenService($this->config, new NanoIdGenerator());

        $this->user = new User(
            id: 'Ov3N',
            name: 'Allan',
            email: 'a@example.com',
            passwordHash: 'irrelevant',
            roles: [
                new Role('R1', 'Admin', ['product:read', 'product:create']),
                new Role('R2', 'Auditor', ['metrics:read']),
            ],
        );
    });

    it('round-trips the principal through issue and verify', function () {
        $result = $this->service->verify($this->service->issue($this->user));

        expect($result->isSuccess())->toBeTrue();

        /** @var UserContext $context */
        $context = $result->getValue();

        expect($context->id)->toBe('Ov3N')
            ->and($context->name)->toBe('Allan')
            ->and($context->email)->toBe('a@example.com')
            ->and($context->roles)->toHaveCount(2);
    });

    it('keeps permissions grouped under the role that grants them', function () {
        /** @var UserContext $context */
        $context = $this->service->verify($this->service->issue($this->user))->getValue();

        // A flat `perms` list could not answer this; the nested table can.
        expect($context->roles[0]->name)->toBe('Admin')
            ->and($context->roles[0]->permissions)->toBe(['product:read', 'product:create'])
            ->and($context->roles[1]->permissions)->toBe(['metrics:read'])
            ->and($context->hasPermission('metrics:read'))->toBeTrue();
    });

    it('carries the principal as one opaque claim, not loose fields', function () {
        $token = $this->service->issue($this->user);
        [, $payload] = explode('.', $token);
        $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

        expect($claims)->toHaveKey('ctx')
            // The readable identity fields must not leak alongside it.
            ->and($claims)->not->toHaveKey('name')
            ->and($claims)->not->toHaveKey('email')
            ->and($claims)->not->toHaveKey('perms')
            // And the claim really is the FlatBuffer, not JSON in disguise.
            ->and(json_decode(base64_decode($claims['ctx']), true))->toBeNull();
    });

    it('rejects a token signed with another secret as 401', function () {
        $foreign = new FirebaseJwtTokenService(
            new JwtConfig(secret: 'a-different-secret-also-32-bytes+'),
            new NanoIdGenerator(),
        );

        $result = $this->service->verify($foreign->issue($this->user));

        expect($result->isSuccess())->toBeFalse()
            ->and(Leaf::getError($result->getErrorId())?->code)->toBe(401);
    });

    it('rejects an expired token', function () {
        $expired = new FirebaseJwtTokenService(new JwtConfig(
            secret: $this->config->secret,
            ttlSeconds: -10,
        ), new NanoIdGenerator());

        expect($this->service->verify($expired->issue($this->user))->isSuccess())->toBeFalse();
    });

    it('rejects a well-signed token whose principal claim is missing', function () {
        $token = JWT::encode(
            ['iss' => 'x', 'iat' => time(), 'exp' => time() + 60, 'sub' => 'Ov3N'],
            $this->config->secret,
            $this->config->algorithm,
        );

        $result = $this->service->verify($token);

        expect($result->isSuccess())->toBeFalse()
            ->and(Leaf::getError($result->getErrorId())?->code)->toBe(401);
    });

    it('rejects garbage in the principal claim without crashing', function () {
        $token = JWT::encode(
            ['exp' => time() + 60, 'ctx' => base64_encode('not a flatbuffer at all')],
            $this->config->secret,
            $this->config->algorithm,
        );

        expect($this->service->verify($token)->isSuccess())->toBeFalse();
    });

    it('never mints the same token twice, even within one second', function () {
        // Every other claim is derived from the user or from a one-second
        // timestamp, so without a unique `jti` two tokens issued back to back
        // are byte-identical. That is not cosmetic: identical tokens hash to the
        // same marker, so rotating one consumes the other and a logout ends an
        // unrelated session.
        $first = $this->service->issueRefresh($this->user);
        $second = $this->service->issueRefresh($this->user);

        expect($first)->not->toBe($second);
    });

    it('refuses an access token where a refresh token is expected, and vice versa', function () {
        // Both are signed with the same key, so only the `typ` claim separates
        // them — without the check, one would silently stand in for the other.
        expect($this->service->verifyRefresh($this->service->issue($this->user))->isSuccess())->toBeFalse()
            ->and($this->service->verify($this->service->issueRefresh($this->user))->isSuccess())->toBeFalse();
    });

    it('round-trips the claim itself, including empty role lists', function () {
        $bare = new TokenUserX('U1', 'Sem papéis', 'x@y.z', []);

        $back = TokenUserXFactory::fromFlatbuffer(TokenUserXFactory::toFlatbuffer($bare));

        expect($back->id)->toBe('U1')->and($back->roles)->toBe([]);

        $withRole = new TokenUserX('U1', 'N', 'x@y.z', [new TokenRoleX('R', 'Papel', [])]);
        $backWithRole = TokenUserXFactory::fromFlatbuffer(TokenUserXFactory::toFlatbuffer($withRole));

        expect($backWithRole->roles[0]->permissions)->toBe([]);
    });
})->group('API', 'Auth');
