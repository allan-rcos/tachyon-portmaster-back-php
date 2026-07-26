<?php

declare(strict_types=1);

namespace API\Auth;

use App\Context\UserContext;
use Domain\Models\IUser;
use Shared\Exceptions\Result;

/**
 * Issues, rotates and revokes the refresh token.
 *
 * The token is a **signed** token carrying the principal ({@see ITokenService}),
 * not an opaque handle. Being self-describing is what lets the refresh endpoint
 * learn who is refreshing without a table mapping handles to users; being
 * `HttpOnly`, `Secure` and `SameSite` in a cookie is what keeps it away from
 * scripts. Opacity was never the thing protecting it.
 *
 * A signature alone, though, cannot be taken back — so revocability comes from a
 * **marker**: issuing records `hash(token) → true` in the `refresh-token` group
 * with the token's own lifetime as the TTL, and every operation that ends a
 * token's life sets that flag to `false`. The marker holds one bit against a
 * digest, so the token itself is never stored anywhere.
 *
 * {@see rotate()} is single-use: refreshing consumes the presented token and
 * returns a new one, so a stolen token stops working the moment the legitimate
 * client refreshes — and vice versa, which makes the theft visible as an
 * unexpected 401 rather than silent.
 */
interface IRefreshTokenService
{
    /**
     * Mints a refresh token for the user and marks it live.
     *
     * Takes the {@see IUser} rather than an id because the token carries the
     * whole principal, roles included.
     *
     * @return Result<string> The token to put in the cookie.
     */
    public function issue(IUser $user): Result;

    /**
     * Consumes a refresh token and issues its replacement.
     *
     * Three things are checked, in order, and all three answer 401: that the
     * token is genuinely ours and unexpired (the signature), that it has not
     * already been used (the marker), and that the account behind it still
     * exists ({@see \App\Services\IValidateSessionUseCase}). Distinguishing them
     * in the response would tell an attacker which of their guesses was close.
     *
     * @return Result<array{user: IUser, token: string}> The revalidated user and
     *         the replacement token, or 401.
     */
    public function rotate(string $token): Result;

    /**
     * Revokes a token by consuming its marker. Idempotent: revoking an unknown
     * or already-consumed token still succeeds, since the caller's goal (that
     * token stops working) is already true.
     *
     * @return Result<null>
     */
    public function revoke(string $token): Result;
}
