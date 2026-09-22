<?php

declare(strict_types=1);

namespace App\Bootstrap;

use Dotenv\Dotenv;

final class App
{
    public function __construct()
    {
        $basePath = dirname(__DIR__, 2);

        if (file_exists($basePath . '/.env')) {
            Dotenv::createImmutable($basePath)->safeLoad();
        }

        date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Africa/Nairobi');

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name($_ENV['SESSION_NAME'] ?? 'dukame_session');

            session_set_cookie_params([
                'httponly' => true,
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'samesite' => 'Lax',
            ]);

            session_start();
        }
    }
}
