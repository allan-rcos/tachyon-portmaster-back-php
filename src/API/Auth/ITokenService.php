<?php

/**
 * Token Service Contract.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Auth;

use App\Context\UserContext;
use Domain\Models\IUser;
use Shared\Exceptions\Result;

/**
 * Mints and verifies the two tokens a session travels on.
 *
 * An **API-layer** port: the token mechanism (JWT, signing algorithm, key
 * management) is a presentation concern. It converts between the domain's
 * {@see IUser} on the way out and the application's {@see UserContext} on the
 * way in — those are the only two types the rest of the system deals in.
 *
 * **Both tokens are signed and self-describing**, and both ride in HTTP-only
 * cookies. The refresh token is deliberately not an opaque handle: the
 * protection against theft comes from the cookie flags (`HttpOnly` against XSS,
 * `Secure`, `SameSite` against CSRF), never from the value being meaningless.
 * Making it meaningful is what lets the refresh endpoint know *who* is
 * refreshing without a lookup table to hold that mapping.
 *
 * What a signature cannot do is expire early, so revocation lives elsewhere: a
 * marker records whether a given refresh token is still live, and the refresh
 * flow consumes it on use — see {@see IRefreshTokenService}.
 *
 * The two tokens differ in lifetime and in a `typ` claim that each verifier
 * checks. Without that claim they would be interchangeable, and each direction
 * of the confusion is its own bug: an access token accepted as a refresh token
 * would quietly stretch a one-hour credential to fourteen days, while a refresh
 * token accepted as an access token would authorize requests with a credential
 * the marker check never sees.
 *
 * @see IRefreshTokenService What makes the refresh token revocable.
 * @see \API\Auth\Interno\FirebaseJwtTokenService The implementation.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface ITokenService
{
    /**
     * Signs the short-lived access token, carrying the user, their roles and
     * each role's permissions.
     *
     * @param  IUser  $user  The principal to encode.
     * @return string The signed token, ready for the cookie.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function issue(IUser $user): string;

    /**
     * Signs the long-lived refresh token for the same principal.
     *
     * Its claims are a snapshot and may well be stale by the time it is used, so
     * the refresh flow re-reads the user instead of trusting them — see
     * {@see \App\Services\IValidateSessionUseCase}.
     *
     * @param  IUser  $user  The principal to encode.
     * @return string The signed token, ready for the cookie.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function issueRefresh(IUser $user): string;

    /**
     * Verifies signature, expiry and type, then rebuilds the caller's context.
     *
     * @param  string  $token  The access token as presented.
     * @return Result<UserContext> Failure (401) when the token is malformed,
     *                             expired, badly signed, or is a refresh token.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function verify(string $token): Result;

    /**
     * The same, for a refresh token.
     *
     * @param  string  $token  The refresh token as presented.
     * @return Result<UserContext> Failure (401) when the token is malformed,
     *                             expired, badly signed, or is an access token.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function verifyRefresh(string $token): Result;
}
