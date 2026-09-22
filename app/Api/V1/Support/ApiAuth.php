<?php
declare(strict_types=1);

namespace App\Api\V1\Support;

use App\Database\Database;
use PDO;

final class ApiAuth
{
    public static function issue(int $userId, ?string $name = null, ?string $expiresAt = null): string
    {
        $plain = bin2hex(random_bytes(32));
        $hash = hash('sha256', $plain);
        $db = Database::connection();

        $stmt = $db->prepare(
            'INSERT INTO api_tokens (user_id, token_hash, name, expires_at)
             VALUES (:user_id, :token_hash, :name, :expires_at)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'token_hash' => $hash,
            'name' => $name,
            'expires_at' => $expiresAt,
        ]);

        return $plain;
    }

    public static function user(): ?array
    {
        $token = Request::bearerToken();
        if (!$token) return null;

        $hash = hash('sha256', $token);
        $db = Database::connection();

        $stmt = $db->prepare(
            'SELECT u.id, u.first_name, u.last_name, u.phone, u.email, u.status,
                    t.id AS token_id
             FROM api_tokens t
             INNER JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = :token_hash
               AND t.revoked_at IS NULL
               AND (t.expires_at IS NULL OR t.expires_at > CURRENT_TIMESTAMP)
             LIMIT 1'
        );
        $stmt->execute(['token_hash' => $hash]);
        $user = $stmt->fetch();

        if (!$user || $user['status'] !== 'active') return null;

        $touch = $db->prepare('UPDATE api_tokens SET last_used_at = CURRENT_TIMESTAMP WHERE id = :id');
        $touch->execute(['id' => $user['token_id']]);

        return $user;
    }

    public static function requireUser(): array
    {
        $user = self::user();
        if (!$user) JsonResponse::error('Authentication required.', 401, 'unauthenticated');
        return $user;
    }

    public static function revokeCurrent(): void
    {
        $token = Request::bearerToken();
        if (!$token) return;

        $stmt = Database::connection()->prepare(
            'UPDATE api_tokens SET revoked_at = CURRENT_TIMESTAMP WHERE token_hash = :token_hash'
        );
        $stmt->execute(['token_hash' => hash('sha256', $token)]);
    }

    public static function shopForUser(int $userId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM shops WHERE owner_id = :user_id AND status <> "suspended"
             ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch() ?: null;
    }
}
