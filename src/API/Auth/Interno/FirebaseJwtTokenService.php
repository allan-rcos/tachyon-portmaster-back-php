<?php

/**
 * Firebase JWT Token Service.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Auth\Interno;

use API\Auth\ITokenService;
use API\Config\JwtConfig;
use API\Negociation\DTO\Token\TokenRoleX;
use API\Negociation\DTO\Token\TokenUserX;
use API\Negociation\DTO\Token\TokenUserXFactory;
use App\Context\RoleContext;
use App\Context\UserContext;
use Domain\ID\IRandomIdGenerator;
use Domain\Models\IRole;
use Domain\Models\IUser;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Google\FlatBuffers\ByteBuffer;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;
use Throwable;

/**
 * {@see ITokenService} backed by firebase/php-jwt (HS256 by default).
 *
 * The principal does not travel as loose claims. It is serialized as a
 * {@see TokenUserX} FlatBuffer, base64'd, and carried in the single
 * {@see CLAIM_PRINCIPAL} claim — see that message for the trade-off this makes.
 * Roles keep their own permission lists, so the context rebuilt on the way in
 * still knows which role granted what.
 *
 * Requires a secret of at least 32 bytes: since ^7.0 (CVE-2025-45769) the
 * library refuses an HMAC key shorter than the digest it signs with. The boot
 * checks it first — see {@see \API\Bootstrap\Chain\JwtChain} — so the failure
 * lands on the environment rather than on the first login.
 *
 * @see ITokenService The contract this implements.
 * @uses JwtConfig Supplies the secret, algorithm, issuer and both lifetimes.
 * @uses IRandomIdGenerator Mints the `jti` that keeps two tokens distinct.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class FirebaseJwtTokenService implements ITokenService
{
    /**
     * The claim holding the base64'd FlatBuffer principal.
     *
     * @var string
     */
    private const string CLAIM_PRINCIPAL = 'ctx';

    /**
     * Discriminates the two token kinds; see {@see ITokenService}.
     *
     * @var string
     */
    private const string CLAIM_TYPE = 'typ';

    /**
     * Value of {@see self::CLAIM_TYPE} on an access token.
     *
     * @var string
     */
    private const string TYPE_ACCESS = 'access';

    /**
     * Value of {@see self::CLAIM_TYPE} on a refresh token.
     *
     * @var string
     */
    private const string TYPE_REFRESH = 'refresh';

    /**
     * @param  JwtConfig  $config  Secret, algorithm, issuer and the two TTLs.
     * @param  IRandomIdGenerator  $ids  Source of the `jti` claim.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private JwtConfig $config,
        private IRandomIdGenerator $ids,
    ) {
    }

    /**
     * Signs an access token with the configured `ttlSeconds`.
     *
     * @param  IUser  $user  The principal to encode.
     * @return string The signed token.
     *
     * @copyright 2026 Tachyon
     */
    public function issue(IUser $user): string
    {
        return $this->sign($user, self::TYPE_ACCESS, $this->config->ttlSeconds);
    }

    /**
     * Signs a refresh token with the configured `refreshTtlSeconds`.
     *
     * @param  IUser  $user  The principal to encode.
     * @return string The signed token.
     *
     * @copyright 2026 Tachyon
     */
    public function issueRefresh(IUser $user): string
    {
        return $this->sign($user, self::TYPE_REFRESH, $this->config->refreshTtlSeconds);
    }

    /**
     * Decodes a token, requiring the access `typ`.
     *
     * @param  string  $token  The access token as presented.
     * @return Result<UserContext> A 401 for any reason it is unusable.
     *
     * @copyright 2026 Tachyon
     */
    public function verify(string $token): Result
    {
        return $this->decode($token, self::TYPE_ACCESS);
    }

    /**
     * Decodes a token, requiring the refresh `typ`.
     *
     * @param  string  $token  The refresh token as presented.
     * @return Result<UserContext> A 401 for any reason it is unusable.
     *
     * @copyright 2026 Tachyon
     */
    public function verifyRefresh(string $token): Result
    {
        return $this->decode($token, self::TYPE_REFRESH);
    }

    /**
     * Builds and signs one token of either kind.
     *
     * Both kinds carry the same principal and differ only in `typ` and lifetime,
     * so they are signed by one method — a second copy would be the natural
     * place for the two to drift apart.
     *
     * @param  IUser  $user  The principal to encode.
     * @param  string  $type  Value for {@see self::CLAIM_TYPE}.
     * @param  int  $ttlSeconds  How far ahead to set `exp`.
     * @return string The signed token.
     *
     * @copyright 2026 Tachyon
     */
    private function sign(IUser $user, string $type, int $ttlSeconds): string
    {
        $now = time();

        $principal = new TokenUserX(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            roles: array_map(
                static fn (IRole $role): TokenRoleX => new TokenRoleX(
                    id: $role->id,
                    name: $role->name,
                    permissions: array_values(array_filter($role->permissions, 'is_string')),
                ),
                $user->roles,
            ),
        );

        $payload = [
            'iss' => $this->config->issuer,
            'iat' => $now,
            'exp' => $now + $ttlSeconds,
            'sub' => $user->id,
            // Without a unique claim two tokens minted for the same user within
            // the same second would be byte-identical — `iat`/`exp` have
            // one-second resolution and every other claim is derived from the
            // user. Identical tokens share a marker, so rotating one would
            // consume the other, and a logout would end an unrelated session.
            // IRandomIdGenerator is the right flavour: unguessable, looked up
            // only by exact match.
            'jti' => $this->ids->generate(),
            self::CLAIM_TYPE => $type,
            self::CLAIM_PRINCIPAL => base64_encode(TokenUserXFactory::toFlatbuffer($principal)->data()),
        ];

        return JWT::encode($payload, $this->config->secret, $this->config->algorithm);
    }

    /**
     * Verifies a token and rebuilds the caller's context from its principal.
     *
     * Every failure — bad signature, expiry, wrong `typ`, unreadable principal —
     * returns the same 401, on purpose.
     *
     * @param  string  $token  The token as presented.
     * @param  string  $expectedType  The `typ` this caller accepts; anything else
     *                                is rejected as invalid rather than merely
     *                                unsuitable.
     * @return Result<UserContext> The rebuilt context, or a 401.
     *
     * @copyright 2026 Tachyon
     */
    private function decode(string $token, string $expectedType): Result
    {
        try {
            $decoded = JWT::decode($token, new Key($this->config->secret, $this->config->algorithm));
        } catch (Throwable) {
            // Never leak which part failed (signature, expiry, shape): to a
            // caller holding a bad token they are all the same answer.
            return self::invalid();
        }

        $claims = (array) $decoded;

        // Both kinds are signed with the same key, so the signature alone does
        // not tell them apart — this check is the only thing that does.
        if (($claims[self::CLAIM_TYPE] ?? null) !== $expectedType) {
            return self::invalid();
        }

        $encoded = $claims[self::CLAIM_PRINCIPAL] ?? null;

        if (!is_string($encoded) || $encoded === '') {
            return self::invalid();
        }

        $binary = base64_decode($encoded, true);
        if ($binary === false) {
            return self::invalid();
        }

        try {
            $principal = TokenUserXFactory::fromFlatbuffer(ByteBuffer::wrap($binary));
        } catch (Throwable) {
            return self::invalid();
        }

        return Result::success(new UserContext(
            id: $principal->id,
            name: $principal->name,
            email: $principal->email,
            roles: array_map(
                static fn (TokenRoleX $role): RoleContext => new RoleContext(
                    id: $role->id,
                    name: $role->name,
                    permissions: $role->permissions,
                ),
                $principal->roles,
            ),
        ));
    }

    /**
     * The single answer every verification failure gets.
     *
     * @return Result<never> A 401 that names no specific cause.
     *
     * @copyright 2026 Tachyon
     */
    private static function invalid(): Result
    {
        return Result::failure(Leaf::newError(new LeafContext(
            message: 'Invalid or expired authentication token.',
            code: 401,
        )));
    }
}
