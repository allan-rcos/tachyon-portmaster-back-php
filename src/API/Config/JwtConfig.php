<?php

declare(strict_types=1);

namespace API\Config;

/**
 * Session token settings.
 *
 * Lives in the **API layer** because JSON Web Tokens are a presentation-level
 * transport decision: how this application happens to carry a session over
 * HTTP. Nothing in `App`, `Domain` or `Infra` knows the session is a JWT, or a
 * cookie, or a token at all — they only ever see a
 * {@see \App\Context\UserContext}.
 *
 * The access token travels in an HTTP-only cookie (never the `Authorization`
 * header), so it is unreachable from JavaScript; the protection comes from the
 * cookie flags: `HttpOnly` (XSS), `Secure` (HTTPS only) and `SameSite` (CSRF).
 *
 * Resolved from the environment at bootstrap (see
 * {@see \API\Bootstrap\DotEnvStarter}) and injected, so no service reads env.
 */
final readonly class JwtConfig
{
    public function __construct(
        public string $secret = '',
        public int $ttlSeconds = 3600,
        public string $issuer = 'tachyon/portmaster',
        public string $algorithm = 'HS256',
        /** Must match the `CookieAuth` scheme documented in swagger.json. */
        public string $cookieName = 'auth_token',
        public bool $cookieSecure = false,
        public string $cookieSameSite = 'Strict',
        /**
         * The refresh token's cookie. Long-lived by design: it is what lets the
         * short-lived access token above expire quickly without logging the
         * user out. The token itself is signed and carries the same principal —
         * the `HttpOnly` flag, not opacity, is what keeps it away from scripts.
         */
        public string $refreshCookieName = 'refresh_token',
        public int $refreshTtlSeconds = 1209600, // 14 days
    ) {
    }
}
