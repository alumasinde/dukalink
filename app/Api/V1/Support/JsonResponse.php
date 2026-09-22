<?php
declare(strict_types=1);

namespace App\Api\V1\Support;

final class JsonResponse
{
    public static function send(mixed $data = null, int $status = 200, ?array $meta = null): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        $body = ['success' => $status < 400];
        if ($status < 400) {
            $body['data'] = $data;
            if ($meta !== null) $body['meta'] = $meta;
        } else {
            $body['error'] = $data;
        }

        echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success(mixed $data = null, int $status = 200, ?array $meta = null): never
    {
        self::send($data, $status, $meta);
    }

    public static function error(string $message, int $status = 400, ?string $code = null): never
    {
        $error = ['message' => $message];
        if ($code !== null) $error['code'] = $code;
        self::send($error, $status);
    }

    public static function methodNotAllowed(array $allowed): never
    {
        header('Allow: ' . implode(', ', $allowed));
        self::error('Method not allowed.', 405, 'method_not_allowed');
    }
}
