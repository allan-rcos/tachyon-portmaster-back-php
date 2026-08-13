<?php

/**
 * Auth Cookie.
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

use API\Config\JwtConfig;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The two HTTP-only cookies that carry the session.
 *
 * `auth_token` holds the short-lived access token; `refresh_token` holds the
 * long-lived one used to mint the next. Both are signed and self-describing
 * ({@see \API\Auth\ITokenService}), and neither is ever put in a response body
 * or the `Authorization` header, so neither is reachable from JavaScript — the
 * protection is entirely in the attributes: `HttpOnly` (XSS), `Secure`
 * (HTTPS-only) and `SameSite` (CSRF).
 *
 * The names match the `CookieAuth` scheme published in swagger.json.
 *
 * @uses JwtConfig Supplies both names, both lifetimes and the flags.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class AuthCookie
{
    /**
     * @param  JwtConfig  $config  Cookie names, lifetimes and security flags.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private JwtConfig $config,
    ) {
    }

    /**
     * The access token carried by the request.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return string|null The token, or null when the cookie is absent or empty.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function read(ServerRequestInterface $request): ?string
    {
        return $this->cookie($request, $this->config->cookieName);
    }

    /**
     * The refresh token carried by the request.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return string|null The token, or null when the cookie is absent or empty.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function readRefresh(ServerRequestInterface $request): ?string
    {
        return $this->cookie($request, $this->config->refreshCookieName);
    }

    /**
     * A `Set-Cookie` value storing the access token for its TTL.
     *
     * @param  string  $token  The signed access token.
     * @return string The header value, ready to append.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function issue(string $token): string
    {
        return $this->build($this->config->cookieName, $token, $this->config->ttlSeconds);
    }

    /**
     * A `Set-Cookie` value storing the refresh token for its (longer) TTL.
     *
     * @param  string  $token  The signed refresh token.
     * @return string The header value, ready to append.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function issueRefresh(string $token): string
    {
        return $this->build($this->config->refreshCookieName, $token, $this->config->refreshTtlSeconds);
    }

    /**
     * A `Set-Cookie` value that immediately expires the access cookie.
     *
     * @return string The header value, ready to append.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function clear(): string
    {
        return $this->build($this->config->cookieName, '', 0);
    }

    /**
     * A `Set-Cookie` value that immediately expires the refresh cookie.
     *
     * @return string The header value, ready to append.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function clearRefresh(): string
    {
        return $this->build($this->config->refreshCookieName, '', 0);
    }

    /**
     * One cookie off the request, treating empty as absent.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @param  string  $name  Cookie to look for.
     * @return string|null Its value, or null.
     *
     * @copyright 2026 Tachyon
     */
    private function cookie(ServerRequestInterface $request, string $name): ?string
    {
        $value = $request->getCookieParams()[$name] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Assembles one `Set-Cookie` value with the configured flags.
     *
     * The same method builds and clears: clearing is an empty value with a
     * `Max-Age` of zero, and the flags must match the ones the cookie was set
     * with or the browser keeps the original.
     *
     * @param  string  $name  Cookie name.
     * @param  string  $value  Its value; empty to clear.
     * @param  int  $maxAge  Lifetime in seconds; zero to expire immediately.
     * @return string The header value.
     *
     * @copyright 2026 Tachyon
     */
    private function build(string $name, string $value, int $maxAge): string
    {
        $attributes = [
            $name.'='.$value,
            'Path=/',
            'Max-Age='.$maxAge,
            'HttpOnly',
            'SameSite='.$this->config->cookieSameSite,
        ];

        if ($this->config->cookieSecure) {
            $attributes[] = 'Secure';
        }

        return implode('; ', $attributes);
    }
}
