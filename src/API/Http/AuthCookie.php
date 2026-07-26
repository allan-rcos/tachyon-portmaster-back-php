<?php

declare(strict_types=1);

namespace API\Http;

use API\Config\JwtConfig;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The two HTTP-only cookies that carry the session.
 *
 * `auth_token` holds the short-lived JWT; `refresh_token` holds the opaque
 * handle used to mint the next one. Neither is ever put in a response body or
 * the `Authorization` header, so neither is reachable from JavaScript — the
 * protection is entirely in the attributes: `HttpOnly` (XSS), `Secure`
 * (HTTPS-only) and `SameSite` (CSRF).
 *
 * The names match the `CookieAuth` scheme published in swagger.json.
 */
final readonly class AuthCookie
{
    public function __construct(
        private JwtConfig $config,
    ) {
    }

    /**
     * The access token carried by the request, or null when absent/empty.
     */
    public function read(ServerRequestInterface $request): ?string
    {
        return $this->cookie($request, $this->config->cookieName);
    }

    /**
     * The refresh token carried by the request, or null when absent/empty.
     */
    public function readRefresh(ServerRequestInterface $request): ?string
    {
        return $this->cookie($request, $this->config->refreshCookieName);
    }

    /**
     * A `Set-Cookie` value storing the access token for its TTL.
     */
    public function issue(string $token): string
    {
        return $this->build($this->config->cookieName, $token, $this->config->ttlSeconds);
    }

    /**
     * A `Set-Cookie` value storing the refresh token for its (longer) TTL.
     */
    public function issueRefresh(string $token): string
    {
        return $this->build($this->config->refreshCookieName, $token, $this->config->refreshTtlSeconds);
    }

    /**
     * A `Set-Cookie` value that immediately expires the access cookie.
     */
    public function clear(): string
    {
        return $this->build($this->config->cookieName, '', 0);
    }

    /**
     * A `Set-Cookie` value that immediately expires the refresh cookie.
     */
    public function clearRefresh(): string
    {
        return $this->build($this->config->refreshCookieName, '', 0);
    }

    private function cookie(ServerRequestInterface $request, string $name): ?string
    {
        $value = $request->getCookieParams()[$name] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

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
