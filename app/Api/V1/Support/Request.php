<?php
declare(strict_types=1);

namespace App\Api\V1\Support;

final class Request
{
    public static function json(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') return [];
        $data = json_decode($raw, true);
        if (!is_array($data)) JsonResponse::error('Request body must be valid JSON.', 422, 'invalid_json');
        return $data;
    }

    public static function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', trim($header), $m)) {
            return trim($m[1]);
        }
        return null;
    }

    public static function integer(string $value): int
    {
        if (!ctype_digit($value) || (int)$value < 1) {
            JsonResponse::error('Invalid resource ID.', 422, 'invalid_id');
        }
        return (int)$value;
    }
}
