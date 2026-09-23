<?php

declare(strict_types=1);

$root = dirname(__DIR__, 4);

require $root . '/vendor/autoload.php';

use App\Bootstrap\App;
use App\Database\Database;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Session;
use App\Support\RateLimiter;
use App\Support\Security;

new App();

if (Auth::check()) {
    header('Location: /dashboard');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        Session::flash('error', 'Your form session expired. Please try again.');
    } else {
        $phone = trim((string)($_POST['phone'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        $rateKey = 'web-login:' . Security::clientIp() . ':' . strtolower($phone);
        $rateMax = max(3, (int)($_ENV['LOGIN_RATE_LIMIT_MAX'] ?? 10));
        $rateWindow = max(60, (int)($_ENV['LOGIN_RATE_LIMIT_WINDOW'] ?? 900));

        if (RateLimiter::tooMany($rateKey, $rateMax, $rateWindow)) {
            Session::flash('error', 'Too many login attempts. Please try again later.');
        } elseif ($phone === '' || $password === '') {
            Session::flash('error', 'Enter your phone number and password.');
        } else {
            $stmt = Database::connection()->prepare(
                'SELECT u.id, u.password_hash, u.status, u.role, s.id AS shop_id, s.slug
                 FROM users u
                 LEFT JOIN shops s ON s.owner_id = u.id AND s.status <> "suspended"
                 WHERE u.phone = :phone
                 LIMIT 1'
            );
            $stmt->execute(['phone' => $phone]);
            $account = $stmt->fetch();

            if (!$account || $account['status'] !== 'active' || !password_verify($password, $account['password_hash'])) {
                Session::flash('error', 'The phone number or password is incorrect.');
            } elseif (($account['role'] ?? 'merchant') === 'admin') {
                // Platform administration uses platform_users and a separate login path.
                Session::flash('error', 'Platform administrator accounts must sign in through the platform administration login.');
            } elseif (!$account['shop_id']) {
                Session::flash('error', 'Your merchant account does not have an active shop yet.');
            } else {
                if (password_needs_rehash($account['password_hash'], PASSWORD_DEFAULT)) {
                    $rehash = Database::connection()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
                    $rehash->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => (int)$account['id']]);
                }
                RateLimiter::clear($rateKey);
                Auth::login((int)$account['id'], (int)$account['shop_id'], (string)$account['slug']);
                header('Location: /dashboard');
                exit;
            }
        }
    }
}

require $root . '/app/Views/auth/login.php';
