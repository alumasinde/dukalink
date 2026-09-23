<?php

declare(strict_types=1);

$root = dirname(__DIR__, 4);
require $root . '/vendor/autoload.php';

use App\Bootstrap\App;
use App\Database\Database;
use App\Support\Csrf;
use App\Support\PlatformAuth;
use App\Support\Session;
use App\Support\RateLimiter;
use App\Support\Security;
use App\Modules\Platform\PlatformAuditService;

new App();
$db = Database::connection();
$audit = new PlatformAuditService($db);
$base = PlatformAuth::basePath();
if (PlatformAuth::check()) { header('Location: ' . $base); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        Session::flash('error', 'Your login session expired. Please try again.');
    } else {
        $login = trim((string)($_POST['login'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $rateKey = 'platform-login:' . Security::clientIp() . ':' . strtolower($login);
        $rateMax = max(3, (int)($_ENV['LOGIN_RATE_LIMIT_MAX'] ?? 10));
        $rateWindow = max(60, (int)($_ENV['LOGIN_RATE_LIMIT_WINDOW'] ?? 900));

        if (RateLimiter::tooMany($rateKey, $rateMax, $rateWindow)) {
            Session::flash('error', 'Too many login attempts. Please try again later.');
        } elseif ($login === '' || $password === '') {
            Session::flash('error', 'Invalid email or password.');
        } else {
            $stmt = $db->prepare(
                'SELECT id, password_hash, status, role FROM platform_users
                 WHERE (phone = :login OR email = :email) LIMIT 1'
            );
            $stmt->execute(['login' => $login, 'email' => $login]);
            $account = $stmt->fetch();
            if (!$account || $account['status'] !== 'active' || !in_array($account['role'], ['admin','super_admin'], true) || !password_verify($password, $account['password_hash'])) {
                Session::flash('error', 'The login details are incorrect.');
            } else {
                $update = $db->prepare('UPDATE platform_users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id');
                $update->execute(['id' => (int)$account['id']]);
                if (password_needs_rehash($account['password_hash'], PASSWORD_DEFAULT)) {
                    $rehash = $db->prepare('UPDATE platform_users SET password_hash = :hash WHERE id = :id');
                    $rehash->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => (int)$account['id']]);
                }
                RateLimiter::clear($rateKey);
                PlatformAuth::login((int)$account['id']);
                $audit->record((int)$account['id'], 'platform_user.login', 'Platform user signed in', 'platform_user', (int)$account['id']);
                header('Location: ' . $base);
                exit;
            }
        }
    }
}
$brand = require $root . '/config/branding.php';
$error = Session::consumeFlash('error');
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Platform Admin Login · <?= htmlspecialchars($brand['name'], ENT_QUOTES, 'UTF-8') ?></title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="/assets/css/variables.css"><link rel="stylesheet" href="/assets/css/app.css"></head><body><main class="min-vh-100 d-flex align-items-center py-5"><div class="container" style="max-width:460px"><div class="text-center mb-4"><div class="fw-bold fs-3"><?= htmlspecialchars($brand['name'], ENT_QUOTES, 'UTF-8') ?></div><div class="text-secondary">Platform administration</div></div><section class="panel p-4"><h1 class="h4 fw-bold mb-1">Platform Admin</h1><p class="text-secondary mb-4">Sign in to manage Dukame platform operations.</p><?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><form method="post" autocomplete="off"><input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>"><div class="mb-3"><label class="form-label">Phone or email</label><input class="form-control" name="login" autocomplete="username" required></div><div class="mb-4"><label class="form-label">Password</label><input class="form-control" type="password" name="password" autocomplete="current-password" required></div><button class="btn btn-primary w-100">Sign in</button></form></section></div></main></body></html>
