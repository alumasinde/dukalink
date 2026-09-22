<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Bootstrap\App;
use App\Database\Database;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Session;

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

        if ($phone === '' || $password === '') {
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
                Auth::login((int)$account['id'], (int)$account['shop_id'], (string)$account['slug']);
                header('Location: /dashboard');
                exit;
            }
        }
    }
}

require dirname(__DIR__) . '/app/Views/auth/login.php';
