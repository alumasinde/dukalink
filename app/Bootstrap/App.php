<?php

declare(strict_types=1);

namespace App\Bootstrap;

use Dotenv\Dotenv;
use App\Support\ErrorHandler;
use App\Support\Security;

final class App
{
    public function __construct()
    {
        $basePath = dirname(__DIR__, 2);

        if (file_exists($basePath . '/.env')) {
            Dotenv::createImmutable($basePath)->safeLoad();
        }

        ErrorHandler::register();
        Security::bootstrap();

        $requestId = Security::requestId();
        $_SERVER['HTTP_X_DUKAME_REQUEST_ID'] = $requestId;
        if (!headers_sent()) {
            header('X-Dukame-Request-Id: ' . $requestId);
        }

        if (Security::isProduction()) {
            $key = base64_decode(trim((string)($_ENV['APP_KEY'] ?? '')), true);
            if ($key === false || strlen($key) !== 32) {
                throw new \RuntimeException('A valid APP_KEY is required in production.');
            }
        }

        date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Africa/Nairobi');

        if (session_status() !== PHP_SESSION_ACTIVE) {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_trans_sid', '0');
            session_name($_ENV['SESSION_NAME'] ?? 'dukame_session');

            session_set_cookie_params([
                'httponly' => true,
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'samesite' => 'Lax',
                'path' => '/',
            ]);

            session_start();
        }
    }
}
