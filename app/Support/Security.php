<?php

declare(strict_types=1);

namespace App\Support;

final class Security
{
    public static function bootstrap(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(self)');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('X-Permitted-Cross-Domain-Policies: none');

        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        if ($https) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    public static function isProduction(): bool
    {
        return strtolower((string)($_ENV['APP_ENV'] ?? 'local')) === 'production';
    }

    public static function clientIp(): string
    {
        // Do not trust X-Forwarded-For by default; configure a trusted proxy before using it.
        return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    }

    public static function requestId(): string
    {
        return (string)($_SERVER['HTTP_X_REQUEST_ID'] ?? '') !== ''
            ? substr((string)$_SERVER['HTTP_X_REQUEST_ID'], 0, 80)
            : bin2hex(random_bytes(12));
    }
}
