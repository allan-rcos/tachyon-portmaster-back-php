<?php

declare(strict_types=1);

namespace API\Bootstrap\Chain;

use API\Bootstrap\BootDraft;
use API\Bootstrap\DotEnvVariables;
use API\Bootstrap\EnvSource;
use API\Config\JwtConfig;
use RuntimeException;

final class JwtChain extends DotEnvChain
{
    /**
     * HS256 signs with the raw secret, so a key shorter than the 256-bit digest
     * weakens the signature it produces — the flaw firebase/php-jwt closed in
     * 7.0 (CVE-2025-45769) by refusing such a key outright.
     */
    private const int MINIMUM_SECRET_BYTES = 32;

    protected function process(EnvSource $env, BootDraft $draft): void
    {
        $defaults = new JwtConfig();

        $secret = $env->string(DotEnvVariables::APP_JWT_SECRET, $defaults->secret);

        // The library would throw on the first token issued instead, which
        // surfaces as a 500 at login and says nothing about the environment.
        // A weak secret is a deployment mistake, so the boot is where it belongs.
        if (strlen($secret) < self::MINIMUM_SECRET_BYTES) {
            throw new RuntimeException(sprintf(
                'APP_JWT_SECRET must be at least %d bytes long; got %d.',
                self::MINIMUM_SECRET_BYTES,
                strlen($secret),
            ));
        }

        $draft->jwt = new JwtConfig(
            secret: $secret,
            ttlSeconds: $env->int(DotEnvVariables::APP_JWT_TTL, $defaults->ttlSeconds),
            issuer: $env->string(DotEnvVariables::APP_JWT_ISSUER, $defaults->issuer),
            cookieName: $env->string(DotEnvVariables::APP_JWT_COOKIE_NAME, $defaults->cookieName),
            cookieSecure: $env->bool(DotEnvVariables::APP_JWT_COOKIE_SECURE, $defaults->cookieSecure),
            refreshCookieName: $env->string(
                DotEnvVariables::APP_REFRESH_COOKIE_NAME,
                $defaults->refreshCookieName,
            ),
            refreshTtlSeconds: $env->int(DotEnvVariables::APP_REFRESH_TTL, $defaults->refreshTtlSeconds),
        );
    }
}
