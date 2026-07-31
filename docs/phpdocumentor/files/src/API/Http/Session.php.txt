<?php

/**
 * Session.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Http;

use App\Context\UserContext;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

/**
 * Reads the caller established by {@see \API\Http\Middleware\AuthenticationMiddleware}.
 *
 * This is all that is left of the old `Authorizer`. It answers **who is
 * calling** (401 when nobody is) and nothing else — deciding what that caller
 * may do now belongs to the use cases, which own their permission and return the
 * 403 themselves. The API layer's remaining job is to establish identity and map
 * failures to status codes.
 *
 * @see \API\Controllers\ResolvesCaller What controllers use to reach this.
 * @see \App\Services\AuthorizesWithPermission Where the 403 is decided instead.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final class Session
{
    /**
     * The caller's context, or a 401 failure when the request is anonymous.
     *
     * Anonymous covers both "no cookie" and "a cookie the middleware rejected":
     * the attribute is simply absent either way, and the two are the same answer
     * to the caller.
     *
     * @return Result<UserContext> The authenticated caller, or a 401.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function require(): Result
    {
        $context = RequestAttributes::AuthenticatedUser->read();

        if (!$context instanceof UserContext) {
            return Result::failure(Leaf::newError(new LeafContext(
                message: 'Authentication is required to access this resource.',
                code: 401,
            )));
        }

        return Result::success($context);
    }
}
