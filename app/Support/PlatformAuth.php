<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

final class PlatformAuth
{
    private const SESSION_KEY = 'platform_user_id';

    public static function check(): bool
    {
        return (int) Session::get(self::SESSION_KEY) > 0;
    }

    public static function login(int $platformUserId): void
    {
        Session::regenerate();
        Session::put(self::SESSION_KEY, $platformUserId);
        Session::forget('user_id');
        Session::forget('shop_id');
        Session::forget('shop_slug');
        Session::forget('user_role');
    }

    public static function logout(): void
    {
        Session::forget(self::SESSION_KEY);
        Session::regenerate();
    }

    public static function requireAdmin(PDO $db): array
    {
        $id = (int) Session::get(self::SESSION_KEY);
        if ($id <= 0) {
            header('Location: ' . self::basePath() . '/login');
            exit;
        }

        $stmt = $db->prepare(
            'SELECT id, first_name, last_name, phone, email, role, status, last_login_at
             FROM platform_users
             WHERE id = :id AND status = \'active\' AND role IN (\'admin\', \'super_admin\')
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        if (!$user) {
            self::logout();
            http_response_code(403);
            exit('Forbidden');
        }

        return $user;
    }

    public static function basePath(): string
    {
        $link = trim((string)($_ENV['ADMIN_LINK'] ?? 'platform'), '/');
        $link = preg_replace('/[^a-zA-Z0-9_-]/', '', $link) ?: 'platform';
        return '/' . strtolower($link);
    }
}
