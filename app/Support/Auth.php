<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

final class Auth
{
    public static function check(): bool
    {
        return (int) Session::get('user_id') > 0 && (int) Session::get('shop_id') > 0;
    }

    public static function login(int $userId, int $shopId, string $slug): void
    {
        Session::regenerate();
        Csrf::regenerate();
        Session::put('user_id', $userId);
        Session::put('shop_id', $shopId);
        Session::put('shop_slug', $slug);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', $params['domain'] ?? '', (bool)($params['secure'] ?? false), (bool)($params['httponly'] ?? true));
        }
        session_destroy();
    }

    public static function requireMerchant(PDO $db): array
    {
        $userId = (int) Session::get('user_id');
        $shopId = (int) Session::get('shop_id');

        if ($userId <= 0 || $shopId <= 0) {
            header('Location: /login');
            exit;
        }

        $stmt = $db->prepare(
            'SELECT s.*, u.first_name, u.last_name, u.phone AS user_phone, u.email, u.role AS user_role
             FROM shops s
             INNER JOIN users u ON u.id = s.owner_id
             WHERE s.id = :shop_id AND s.owner_id = :user_id AND u.role = \'merchant\'
             LIMIT 1'
        );
        $stmt->execute(['shop_id' => $shopId, 'user_id' => $userId]);
        $shop = $stmt->fetch();

        if (!$shop || $shop['status'] === 'suspended') {
            self::logout();
            header('Location: /login');
            exit;
        }

        return $shop;
    }

}
