<?php

declare(strict_types=1);

namespace App\Support;

use Throwable;

final class ErrorHandler
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) return false;
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (Throwable $e): void {
            $requestId = (string)($_SERVER['HTTP_X_DUKAME_REQUEST_ID'] ?? Security::requestId());
            error_log(sprintf('[Dukame %s] %s: %s in %s:%d', $requestId, $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));

            $isApi = str_starts_with(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/api/');
            http_response_code(500);
            if ($isApi) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => ['message' => 'An unexpected server error occurred.', 'code' => 'server_error', 'request_id' => $requestId],
                ], JSON_UNESCAPED_SLASHES);
                return;
            }

            $debug = strtolower((string)($_ENV['APP_ENV'] ?? 'local')) !== 'production' && filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($debug) {
                echo '<pre style="white-space:pre-wrap;padding:2rem;font-family:monospace">' . htmlspecialchars($e->__toString(), ENT_QUOTES, 'UTF-8') . '</pre>';
                return;
            }
            echo '<main style="max-width:720px;margin:10vh auto;padding:2rem;font-family:system-ui"><h1>Something went wrong</h1><p>Please try again. If the problem continues, provide this reference to support: <strong>' . htmlspecialchars($requestId, ENT_QUOTES, 'UTF-8') . '</strong></p></main>';
        });
    }
}
